<?php

namespace Kraken\Framework\Core\Provider;

use Kraken\Config\Config;
use Kraken\Config\ConfigFactory;
use Kraken\Config\ConfigInterface;
use Kraken\Config\Overwrite\OverwriteMerger;
use Kraken\Config\Overwrite\OverwriteReverseIsolater;
use Kraken\Config\Overwrite\OverwriteReverseMerger;
use Kraken\Config\Overwrite\OverwriteReverseReplacer;
use Kraken\Core\CoreInterface;
use Kraken\Core\CoreInputContextInterface;
use Kraken\Core\Service\ServiceProvider;
use Kraken\Core\Service\ServiceProviderInterface;
use Kraken\Filesystem\Filesystem;
use Kraken\Filesystem\FilesystemAdapterFactory;
use Kraken\Runtime\Runtime;
use Kraken\Util\Support\ArraySupport;
use Kraken\Util\Support\StringSupport;

class ConfigProvider extends ServiceProvider implements ServiceProviderInterface
{
    /**
     * @var string[]
     */
    protected $requires = [
        'Kraken\Core\CoreInputContextInterface'
    ];

    /**
     * @var string[]
     */
    protected $provides = [
        'Kraken\Config\ConfigInterface'
    ];

    /**
     * @var CoreInterface
     */
    private $core;

    /**
     * @var CoreInputContextInterface
     */
    private $context;

    /**
     * @param CoreInterface $core
     */
    protected function register(CoreInterface $core)
    {
        $context = $core->make('Kraken\Core\CoreInputContextInterface');

        $this->core = $core;
        $this->context = $context;

        $dir  = $this->getDir($context->getName(), $context->getType());
        $name = $context->getName();

        $prefix   = $core->getDataPath() . '/config/' . $dir;
        $typePath = $prefix . '/config\.([a-zA-Z]*?)$';
        $namePath = $prefix . '/' . $name . '/config\.([a-zA-Z]*?)$';

        $path = is_dir($prefix . '/' . $name) ? $namePath : $typePath;

        $data = $core->config();
        $data['imports'] = [];
        $data['imports'][] = [
            'resource' => $path,
            'mode'     => 'merge'
        ];

        $config = new Config($data);
        $config->setOverwriteHandler(new OverwriteReverseMerger);
        $this->configure($config);
        $config->setOverwriteHandler(new OverwriteMerger);

        $vars = array_merge(
            $config->exists('vars') ? $config->get('vars') : [],
            $this->getDefaultVariables()
        );

        $records = ArraySupport::flatten($config->getAll());
        foreach ($records as $key=>$value)
        {
            $new = StringSupport::parametrize($value, $vars);
            if (is_string($value) && $new != $value)
            {
                $config->set($key, $new);
            }
        }

        $core->instance(
            'Kraken\Config\ConfigInterface',
            $config
        );
    }

    /**
     * @param CoreInterface $core
     */
    protected function unregister(CoreInterface $core)
    {
        unset($this->core);
        unset($this->context);

        $core->remove(
            'Kraken\Config\ConfigInterface'
        );
    }

    /**
     * @param string $path
     * @return ConfigInterface
     */
    private function createConfig($path)
    {
        $factory = new FilesystemAdapterFactory();

        $path = explode('/', $path);
        $file = array_pop($path);
        $path = implode('/', $path);

        return (new ConfigFactory(
            new Filesystem(
                $factory->create('Local', [ [ 'path' => $path ] ])
            ),
            [ '#' . $file . '#si' ]
        ))->create();
    }

    /**
     * @param string $name
     * @param string $type
     * @return string
     */
    private function getDir($name, $type)
    {
        if ($name === Runtime::RESERVED_CONSOLE_CLIENT || $name === Runtime::RESERVED_CONSOLE_SERVER)
        {
            return 'Console';
        }

        return $type;
    }

    /**
     * @param string $option
     * @return callable|null
     */
    private function getOverwriteHandler($option)
    {
        switch ($option)
        {
            case 'isolate':     return new OverwriteReverseIsolater();
            case 'replace':     return new OverwriteReverseReplacer();
            case 'merge':       return new OverwriteReverseMerger();
            default:            return null;
        }
    }

    /**
     * @param ConfigInterface $config
     */
    private function configure(ConfigInterface $config)
    {
        if ($config->exists('imports'))
        {
            $resources = (array) $config->get('imports');
        }
        else
        {
            $resources = [];
        }

        foreach ($resources as $resource)
        {
            $handler = isset($resource['mode'])
                ? $this->getOverwriteHandler($resource['mode'])
                : null
            ;

            $path = StringSupport::parametrize(
                $resource['resource'],
                $this->getDefaultVariables()
            );

            $current = $this->createConfig($path);

            $this->configure($current);

            $config->merge($current->getAll(), $handler);
        }
    }

    /**
     * @return string[]
     */
    private function getDefaultVariables()
    {
        $core    = $this->core;
        $context = $this->context;

        return [
            'runtime'   => $context->getType(),
            'parent'    => $context->getParent(),
            'alias'     => $context->getAlias(),
            'name'      => $context->getName(),
            'basepath'  => $core->getBasePath(),
            'datapath'  => $core->getDataPath(),
            'localhost' => '127.0.0.1'
        ];
    }
}