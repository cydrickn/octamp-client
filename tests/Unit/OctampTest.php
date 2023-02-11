<?php

namespace Octamp\Client\Tests\Unit;

use Octamp\Client\Connection;
use Octamp\Client\Octamp;

class OctampTest extends TestCase
{
    public function testVersion()
    {
        $version = Octamp::version();
        $this->assertIsString($version);
    }

    public function testConnection()
    {
        $connection = Octamp::connection('localhost', 80);
        $this->assertInstanceOf(Connection::class, $connection);
    }
}
