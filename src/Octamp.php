<?php

namespace Octamp\Client;

use Composer\InstalledVersions;

class Octamp
{
    public static function version()
    {
        return InstalledVersions::getVersion('octamp/client');
    }

    public static function connection(string $host, int $port, array $option = []): Connection
    {
        return new Connection($host, $port, $option);
    }
}
