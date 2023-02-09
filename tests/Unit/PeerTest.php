<?php

namespace Octamp\Client\Tests\Unit;

use Co\Http\Client;
use Octamp\Client\Peer;

class PeerTest extends TestCase
{
    public function testOpen()
    {
        $peer = new Peer('localhost', 9000);
        $client = \Mockery::mock(Client::class);
        $client->shouldIgnoreMissing();
        $peer->setClient($client);
        $client->shouldReceive('upgrade')
            ->once()
            ->with('/')
            ->andReturn(false);

        $peer->open();
    }
}
