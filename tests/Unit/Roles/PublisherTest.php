<?php

namespace Octamp\Client\Tests\Unit\Roles;

use Octamp\Client\Promise\PromiseInterface;
use Octamp\Client\Roles\Publisher;
use Octamp\Client\Session;
use Octamp\Client\Tests\AssertCoroutine;
use Octamp\Client\Tests\Unit\TestCase;
use Thruway\Message\ErrorMessage;
use Thruway\Message\Message;
use Thruway\Message\PublishedMessage;
use Thruway\Message\PublishMessage;
use Thruway\Message\SubscribeMessage;

class PublisherTest extends TestCase
{
    public function testGetFeatures()
    {
        $publisher = new Publisher();
        $features = $publisher->getFeatures();
        $this->assertInstanceOf(\stdClass::class, $features);
    }

    public function testHandlesMessage()
    {
        $publisher = new Publisher();
        $message = new PublishedMessage(1, 1);
        $result = $publisher->handlesMessage($message);
        $this->assertTrue($result);
    }

    public function testHandlesMessageFalse()
    {
        $publisher = new Publisher();
        $message = new PublishMessage(1, (object) [], 'octamp.publish');
        $result = $publisher->handlesMessage($message);
        $this->assertFalse($result);
    }

    public function testHandlesMessageError()
    {
        $publisher = new Publisher();
        $message = new ErrorMessage(Message::MSG_PUBLISH, 1, (object) [], '');
        $result = $publisher->handlesMessage($message);
        $this->assertTrue($result);
    }

    public function testProcessPublish()
    {
        $session = \Mockery::mock(Session::class);
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::type(PublishMessage::class))
            ->once();

        $publisher = new Publisher();
        $result = $publisher->publish($session, 'octamp.test');
        $this->assertNull($result);
    }

    public function testProcessPublishAcknowledge()
    {
        $session = \Mockery::mock(Session::class);
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::type(PublishMessage::class))
            ->once();

        $publisher = new Publisher();
        $result = $publisher->publish($session, 'octamp.test', [], [], ['acknowledge' => true]);
        $this->assertInstanceOf(PromiseInterface::class, $result);
    }

    public function testProcessPublishedMessage()
    {
        $requestId = null;
        $session = \Mockery::mock(Session::class);
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::on(function ($message) use (&$requestId) {
                if (!($message instanceof PublishMessage)) {
                    return false;
                }
                $requestId = $message->getRequestId();

                return true;
            }))
            ->once();


        $publisher = new Publisher();
        $promise = $publisher->publish($session, 'octamp.test', [], [], ['acknowledge' => true]);
        $message = new PublishedMessage($requestId, 1);
        $publisher->onMessage($session, $message);

        $assert = new AssertCoroutine();
        $promise->then(function ($publication) use ($assert) {
            $assert->add([$this, 'assertSame'], 1, $publication);
        });
        $assert->assert();
    }

    public function testProcessErrorMessage()
    {
        $requestId = null;
        $session = \Mockery::mock(Session::class);
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::on(function ($message) use (&$requestId) {
                if (!($message instanceof PublishMessage)) {
                    return false;
                }
                $requestId = $message->getRequestId();

                return true;
            }))
            ->once();


        $publisher = new Publisher();
        $promise = $publisher->publish($session, 'octamp.test', [], [], ['acknowledge' => true]);
        $message = new ErrorMessage(Message::MSG_PUBLISH, $requestId, (object) [], '');
        $publisher->onMessage($session, $message);

        $assert = new AssertCoroutine();
        $promise->catch(function ($error) use ($assert) {
            $assert->add([$this, 'assertInstanceOf'], ErrorMessage::class, $error);
            $assert->add([$this, 'assertSame'], Message::MSG_ERROR, $error->getMsgCode());
            $assert->add([$this, 'assertSame'], Message::MSG_PUBLISH, $error->getErrorMsgCode());
        });
        $assert->assert();
    }

    public function testOnMessageError()
    {
        $session = \Mockery::mock(Session::class);
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::type(ErrorMessage::class))
            ->once();


        $publisher = new Publisher();
        $message = new SubscribeMessage(1, (object) [], 'octamp.test');
        $publisher->onMessage($session, $message);
    }
}
