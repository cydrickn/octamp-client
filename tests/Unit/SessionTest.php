<?php

namespace Octamp\Client\Tests\Unit;

use Octamp\Client\Peer;
use Octamp\Client\Promise\Deferred;
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

    public function testGetId()
    {
        $peer = \Mockery::mock(Peer::class);
        $session = new Session($peer);

        $session->setSessionId(100);
        $this->assertSame(100, $session->getId());
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

    public function testGetRealm()
    {
        $peer = \Mockery::mock(Peer::class);
        $session = new Session($peer);

        $session->setDetails(['realm' => 'realm1']);
        $this->assertSame('realm1', $session->getRealm());
    }

    public function testGetFeatures()
    {
        $peer = \Mockery::mock(Peer::class);
        $session = new Session($peer);

        $jsonRole = '{"broker":{"features":{"publisher_identification":true,"pattern_based_subscription":true,"session_meta_api":true,"subscription_meta_api":true,"subscriber_blackwhite_listing":true,"publisher_exclusion":true,"subscription_revocation":true,"payload_transparency":true,"payload_encryption_cryptobox":true,"event_retention":true}},"dealer":{"features":{"caller_identification":true,"pattern_based_registration":true,"session_meta_api":true,"registration_meta_api":true,"shared_registration":true,"call_canceling":true,"progressive_call_results":true,"registration_revocation":true,"payload_transparency":true,"testament_meta_api":true,"payload_encryption_cryptobox":true}}}';

        $session->setDetails(['realm' => 'realm1', 'roles' => (object) json_decode($jsonRole, true)]);
        $this->assertEquals((object) json_decode($jsonRole, true), $session->getFeatures());
    }

    public function testGetSubscriptions()
    {
        $peer = \Mockery::mock(Peer::class);
        $subscriber = \Mockery::mock(Subscriber::class);
        $session = new Session($peer);

        $peer->shouldReceive('getSubscriber')
            ->once()
            ->andReturn($subscriber);
        $subscriptions = [[
            'topic_name' => 'test',
            'callback' => function () {},
            'request_id' => 1,
            'options' => [],
            'deferred' => new Deferred(),
        ]];
        $subscriber->shouldReceive('getSubscriptions')
            ->once()
            ->andReturn($subscriptions);


        $this->assertSame($subscriptions, $session->getSubscriptions());
    }

    public function testGetRegistrations()
    {
        $peer = \Mockery::mock(Peer::class);
        $callee = \Mockery::mock(Callee::class);
        $session = new Session($peer);

        $peer->shouldReceive('getCallee')
            ->once()
            ->andReturn($callee);
        $registrations = [[
            'procedure_name' => 'test',
            'callback' => function () {},
            'request_id' => 1,
            'options' => [],
            'futureResult' => new Deferred(),
        ]];
        $callee->shouldReceive('getRegistrations')
            ->once()
            ->andReturn($registrations);


        $this->assertSame($registrations, $session->getRegistrations());
    }
}
