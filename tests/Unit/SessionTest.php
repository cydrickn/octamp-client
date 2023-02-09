<?php

namespace Octamp\Client\Tests\Unit;

use Octamp\Client\Peer;
use Octamp\Client\Promise\ProgressablePromiseInterface;
use Octamp\Client\Promise\PromiseInterface;
use Octamp\Client\Roles\Callee;
use Octamp\Client\Roles\Caller;
use Octamp\Client\Roles\Publisher;
use Octamp\Client\Roles\Subscriber;
use Octamp\Client\Session;
use Thruway\Message\Message;

class SessionTest extends TestCase
{
    public function testState()
    {
        $peer = \Mockery::mock(Peer::class);
        $session = new Session($peer);

        $session->setState(Session::STATE_UNKNOWN);
        $this->assertSame(Session::STATE_UNKNOWN, $session->getState());
    }

    public function testSessionId()
    {
        $peer = \Mockery::mock(Peer::class);
        $session = new Session($peer);

        $session->setSessionId(100);
        $this->assertSame(100, $session->getSessionId());
    }

    public function testSendMessage()
    {
        $peer = \Mockery::mock(Peer::class);
        $session = new Session($peer);

        $peer->shouldReceive('sendMessage')
            ->once()
            ->with(\Mockery::capture($messagePassed))
        ;
        $message = \Mockery::mock(Message::class);
        $session->sendMessage($message);

        $this->assertSame($message, $messagePassed);
    }

    public function testSubscribe()
    {
        $peer = \Mockery::mock(Peer::class);
        $subscriber = \Mockery::mock(Subscriber::class);
        $session = new Session($peer);

        $peer->shouldReceive('getSubscriber')
            ->once()
            ->andReturn($subscriber);

        $promise = \Mockery::mock(PromiseInterface::class);
        $subscriber->shouldReceive('subscribe')
            ->once()
            ->with($session, 'topic', \Mockery::type('callable'), [])
            ->andReturn($promise);


        $result = $session->subscribe('topic', function () {});
        $this->assertInstanceOf(PromiseInterface::class, $result);
    }

    public function testPublish()
    {
        $peer = \Mockery::mock(Peer::class);
        $publisher = \Mockery::mock(Publisher::class);
        $session = new Session($peer);
        $peer->shouldReceive('getPublisher')
            ->once()
            ->andReturn($publisher);

        $promise = \Mockery::mock(PromiseInterface::class);
        $publisher->shouldReceive('publish')
            ->once()
            ->with($session, 'topic', [], [], [])
            ->andReturn($promise);

        $result = $session->publish('topic');
        $this->assertInstanceOf(PromiseInterface::class, $result);
    }

    public function testCall()
    {
        $peer = \Mockery::mock(Peer::class);
        $caller = \Mockery::mock(Caller::class);
        $session = new Session($peer);
        $peer->shouldReceive('getCaller')
            ->once()
            ->andReturn($caller);

        $promise = \Mockery::mock(ProgressablePromiseInterface::class);
        $caller->shouldReceive('call')
            ->once()
            ->with($session, 'topic', [], [], [])
            ->andReturn($promise);

        $result = $session->call('topic');
        $this->assertInstanceOf(ProgressablePromiseInterface::class, $result);
    }

    public function testRegister()
    {
        $peer = \Mockery::mock(Peer::class);
        $callee = \Mockery::mock(Callee::class);
        $session = new Session($peer);
        $peer->shouldReceive('getCallee')
            ->once()
            ->andReturn($callee);

        $promise = \Mockery::mock(ProgressablePromiseInterface::class);
        $callee->shouldReceive('register')
            ->once()
            ->with($session, 'topic', \Mockery::type('callable'), [])
            ->andReturn($promise);

        $result = $session->register('topic', function () {});
        $this->assertInstanceOf(ProgressablePromiseInterface::class, $result);
    }

    public function testUnregister()
    {
        $peer = \Mockery::mock(Peer::class);
        $callee = \Mockery::mock(Callee::class);
        $session = new Session($peer);
        $peer->shouldReceive('getCallee')
            ->once()
            ->andReturn($callee);

        $promise = \Mockery::mock(ProgressablePromiseInterface::class);
        $callee->shouldReceive('unregister')
            ->once()
            ->with($session, 'topic')
            ->andReturn($promise);

        $result = $session->unregister('topic');
        $this->assertInstanceOf(ProgressablePromiseInterface::class, $result);
    }
}
