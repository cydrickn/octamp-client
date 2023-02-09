<?php

namespace Octamp\Client\Tests\Unit;

use Co\Http\Client;
use Octamp\Client\Peer;
use Octamp\Client\Roles\Callee;
use Octamp\Client\Roles\Caller;
use Octamp\Client\Roles\Publisher;
use Octamp\Client\Roles\Subscriber;
use Octamp\Client\Session;
use Octamp\Client\Tests\AssertCoroutine;
use Swoole\WebSocket\Frame;
use Thruway\Message\ChallengeMessage;
use Thruway\Message\HelloMessage;
use Thruway\Message\Message;
use Thruway\Message\PublishedMessage;
use Thruway\Message\WelcomeMessage;

class PeerTest extends TestCase
{
    protected function getClient()
    {
        $client = \Mockery::mock(Client::class);
        $client->shouldReceive('setHeaders');
        $client->shouldReceive('close');
        $client->shouldReceive('push');
        $client->shouldReceive('upgrade')->andReturn(true);

        return $client;
    }

    public function testOpen()
    {
        $peer = new Peer('localhost', 9000);
        $client = \Mockery::mock(Client::class);
        $peer->setClient($client);
        $client->shouldReceive('setHeaders')
            ->once();
        $client->shouldReceive('upgrade')
            ->once()
            ->with('/')
            ->andReturn(false);
        $client->shouldReceive('close')
            ->once();

        $peer->open();
        $peer->close('Test');
    }

    public function testSendMessage()
    {
        $peer = new Peer('localhost', 9000);
        $client = \Mockery::mock(Client::class);
        $peer->setClient($client);

        $message = new WelcomeMessage(1, (object) []);
        $client->shouldReceive('push')
            ->once()
            ->with(json_encode($message));

        $peer->sendMessage($message);
    }

    public function testStartSession()
    {
        $peer = new Peer('localhost', 9000);
        $client = \Mockery::mock(Client::class);
        $client->shouldReceive('setHeaders');
        $client->shouldReceive('close');
        $client->shouldReceive('upgrade')
            ->once()
            ->andReturn(true);

        $client->shouldReceive('push')
            ->once()
            ->with('[1,"realm1",{"roles":{"publisher":{"features":{"publisher_identification":true,"subscriber_blackwhite_listing":true,"publisher_exclusion":true}},"subscriber":{"features":{"publisher_identification":true,"pattern_based_subscription":true,"subscription_revocation":true}},"caller":{"features":{"caller_identification":true,"progressive_call_results":true}},"callee":{"features":{"caller_identification":true,"pattern_based_registration":true,"shared_registration":true,"progressive_call_results":true,"registration_revocation":true}}},"authmethods":[],"authid":"anonymous"}]');

        $peer->setClient($client);

        $peer->open();
        $peer->close('Test');
    }

    public function testGetPublisher()
    {
        $peer = new Peer('localhost', 9000);
        $peer->setClient($this->getClient());

        $peer->open();
        $result = $peer->getPublisher();
        $peer->close('Test');

        $this->assertInstanceOf(Publisher::class, $result);
    }

    public function testGetSubscriber()
    {
        $peer = new Peer('localhost', 9000);
        $peer->setClient($this->getClient());

        $peer->open();
        $result = $peer->getSubscriber();
        $peer->close('Test');

        $this->assertInstanceOf(Subscriber::class, $result);
    }

    public function testGetCaller()
    {
        $peer = new Peer('localhost', 9000);
        $peer->setClient($this->getClient());

        $peer->open();
        $result = $peer->getCaller();
        $peer->close('Test');

        $this->assertInstanceOf(Caller::class, $result);
    }

    public function testGetCallee()
    {
        $peer = new Peer('localhost', 9000);
        $peer->setClient($this->getClient());

        $peer->open();
        $result = $peer->getCallee();
        $peer->close('Test');

        $this->assertInstanceOf(Callee::class, $result);
    }

    public function testProcessWelcome()
    {
        $peer = new Peer('localhost', 9000);
        $client = $this->getClient();
        $client->connected = true;
        $message = new WelcomeMessage(1, (object) []);

        $frame = new Frame();
        $frame->finish = true;
        $frame->data = json_encode($message);
        $frame->fd = 1;
        $frame->opcode = WEBSOCKET_OPCODE_TEXT;

        $client->shouldReceive('recv')
            ->andReturn($frame);

        $peer->setClient($client);
        $assert = new AssertCoroutine();
        $peer->onOpen(function ($session) use ($client, $assert) {
            $assert->add([$this, 'assertInstanceOf'], Session::class, $session);
            $client->connected = false;
        });
        $peer->open();
        while ($client->connected) {
            usleep(1);
        }
        $assert->assert();
    }

    public function testProcessChallenge()
    {
        $peer = new Peer('localhost', 9000);
        $client = $this->getClient();
        $client->connected = true;
        $message = new ChallengeMessage('wampcra', ['challenge' => '123']);

        $frame = new Frame();
        $frame->finish = true;
        $frame->data = json_encode($message);
        $frame->fd = 1;
        $frame->opcode = WEBSOCKET_OPCODE_TEXT;

        $client->shouldReceive('recv')
            ->andReturn($frame);

        $peer->setClient($client);
        $assert = new AssertCoroutine();
        $peer->onChallenge(function ($session, $method, $extra) use ($client, $assert) {
            $assert->add([$this, 'assertInstanceOf'], Session::class, $session);
            $assert->add([$this, 'assertSame'], 'wampcra', $method);
            $assert->add([$this, 'assertSame'], ['challenge' => '123'], (array) $extra);
            $client->connected = false;
        });
        $peer->open();
        while ($client->connected) {
            usleep(1);
        }
        $assert->assert();
    }

    public function testProcessOthers()
    {
        $peer = new Peer('localhost', 9000);
        $client = $this->getClient();
        $client->connected = true;
        $message = new PublishedMessage(1, 1);

        $frame = new Frame();
        $frame->finish = true;
        $frame->data = json_encode($message);
        $frame->fd = 1;
        $frame->opcode = WEBSOCKET_OPCODE_TEXT;

        $client->shouldReceive('recv')
            ->andReturn($frame);

        $peer->setClient($client);
        $assert = new AssertCoroutine();

        $publisher = \Mockery::mock(new Publisher());
        $publisher->shouldReceive('onMessage')
            ->once()
            ->with(\Mockery::type(Session::class), \Mockery::on(function (Message $message) use ($client, $assert) {
                $assert->add([$this, 'assertInstanceOf'], PublishedMessage::class, $message);
                $client->connected = false;

                return true;
            }));
        $peer->setPublisher($publisher);

        $peer->open();
        while ($client->connected) {
            usleep(1);
        }
        $assert->assert();
    }

    public function testSetCallee()
    {
        $peer = new Peer('localhost', 9000);
        $peer->setCallee(new Callee());
        $this->assertTrue($peer->hasRole('callee'));
    }

    public function testSetPublisher()
    {
        $peer = new Peer('localhost', 9000);
        $peer->setPublisher(new Publisher());
        $this->assertTrue($peer->hasRole('publisher'));
    }

    public function testSetSubscriber()
    {
        $peer = new Peer('localhost', 9000);
        $peer->setSubscriber(new Subscriber());
        $this->assertTrue($peer->hasRole('subscriber'));
    }

    public function testSetCaller()
    {
        $peer = new Peer('localhost', 9000);
        $peer->setCaller(new Caller());
        $this->assertTrue($peer->hasRole('caller'));
    }

    public function testHasRole()
    {
        $peer = new Peer('localhost', 9000);
        $peer->setCaller(new Caller());
        $this->assertTrue($peer->hasRole('caller'));
    }

    public function testGetSession()
    {
        $peer = new Peer('localhost', 9000);
        $peer->setClient($this->getClient());

        $peer->open();
        $result = $peer->getSession();
        $peer->close('Test');

        $this->assertInstanceOf(Session::class, $result);
    }
}
