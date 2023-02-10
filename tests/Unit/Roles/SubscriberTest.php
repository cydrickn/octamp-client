<?php

namespace Octamp\Client\Tests\Unit\Roles;

use Octamp\Client\Promise\ProgressablePromiseInterface;
use Octamp\Client\Roles\Subscriber;
use Octamp\Client\Session;
use Octamp\Client\Tests\AssertCoroutine;
use Octamp\Client\Tests\Unit\TestCase;
use Thruway\Message\CallMessage;
use Thruway\Message\ErrorMessage;
use Thruway\Message\EventMessage;
use Thruway\Message\Message;
use Thruway\Message\SubscribedMessage;
use Thruway\Message\SubscribeMessage;

class SubscriberTest extends TestCase
{
    use RoleTrait;

    public static function handlesMessageProvider()
    {
        yield [new SubscribedMessage(1, 1), true];
        yield [new ErrorMessage(Message::MSG_SUBSCRIBE, 1,  new \stdClass(), ''), true];
        yield [new CallMessage(1,  new \stdClass(), 'octamp.test'), false];
    }

    public function getRoleClass()
    {
        return Subscriber::class;
    }

    public function testSubscribe()
    {
        $session = \Mockery::mock(Session::class);
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::type(SubscribeMessage::class))
            ->once();

        $role = new Subscriber();
        $result = $role->subscribe($session, 'octamp.test', function () {});
        $this->assertInstanceOf(ProgressablePromiseInterface::class, $result);
    }

    public function testProcessSubscribed()
    {
        $requestId = null;
        $session = \Mockery::mock(Session::class);
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::on(function ($message) use (&$requestId) {
                if (!($message instanceof SubscribeMessage)) {
                    return false;
                }
                $requestId = $message->getRequestId();

                return true;
            }))
            ->once();

        $assert = new AssertCoroutine();
        $role = new Subscriber();
        $promise = $role->subscribe($session, 'octamp.test', function () {});

        $message = new SubscribedMessage($requestId, 1);
        $role->onMessage($session, $message);
        $promise->then(function ($result) use ($assert, $message) {
            $assert->add([$this, 'assertSame'], $message, $result);
            $assert->done();
        });

        $assert->assertWait();
    }

    public function testProcessErrorMessage()
    {
        $requestId = null;
        $session = \Mockery::mock(Session::class);
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::on(function ($message) use (&$requestId) {
                if (!($message instanceof SubscribeMessage)) {
                    return false;
                }
                $requestId = $message->getRequestId();

                return true;
            }))
            ->once();


        $role = new Subscriber();
        $promise = $role->subscribe($session, 'octamp.test', function () {});
        $message = new ErrorMessage(Message::MSG_SUBSCRIBE, $requestId, (object) [], '');
        $role->onMessage($session, $message);

        $assert = new AssertCoroutine();
        $promise->catch(function ($error) use ($assert) {
            $assert->add([$this, 'assertInstanceOf'], ErrorMessage::class, $error);
            $assert->add([$this, 'assertSame'], Message::MSG_ERROR, $error->getMsgCode());
            $assert->add([$this, 'assertSame'], Message::MSG_SUBSCRIBE, $error->getErrorMsgCode());
            $assert->done();
        });
        $assert->assertWait();
    }

    public function testOnMessageError()
    {
        $session = \Mockery::mock(Session::class);
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::type(ErrorMessage::class))
            ->once();

        $role = new Subscriber();
        $message = new SubscribeMessage(1, (object) [], 'octamp.test');
        $role->onMessage($session, $message);
    }

    public function testProcessEvent()
    {
        $requestId = null;
        $session = \Mockery::mock(Session::class);
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::on(function ($message) use (&$requestId) {
                if (!($message instanceof SubscribeMessage)) {
                    return false;
                }
                $requestId = $message->getRequestId();

                return true;
            }))
            ->once();

        $assert = new AssertCoroutine();
        $role = new Subscriber();
        $role->subscribe($session, 'octamp.test', function ($args) use ($assert) {
            $assert->add([$this, 'assertSame'], ['hello'], $args);
            $assert->done();
        });

        $message = new SubscribedMessage($requestId, 1);
        $role->onMessage($session, $message);

        $message = new EventMessage(1, 1, new \stdClass(), ['hello'], [], 'octamp.test');
        $role->onMessage($session, $message);

        $assert->assertWait();
    }
}
