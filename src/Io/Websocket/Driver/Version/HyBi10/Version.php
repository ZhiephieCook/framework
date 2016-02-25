<?php

namespace Kraken\Io\Websocket\Driver\Version\HyBi10;

use Kraken\Io\Http\HttpRequestInterface;
use Kraken\Io\Websocket\Driver\Version\RFC6455\Version as VersionRFC6455;
use Kraken\Io\Websocket\Driver\Version\VersionInterface;

class Version extends VersionRFC6455 implements VersionInterface
{
    /**
     * @override
     */
    public function isRequestSupported(HttpRequestInterface $request)
    {
        $version = (int)(string)$request->getHeaderLine('Sec-WebSocket-Version');

        return ($version >= 6 && $version < 13);
    }

    /**
     * @override
     */
    public function getVersionNumber()
    {
        return 6;
    }
}
