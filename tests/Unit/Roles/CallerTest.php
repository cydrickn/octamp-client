<?php

namespace Octamp\Client\Tests\Unit\Roles;

use Octamp\Client\Promise\ProgressablePromiseInterface;
use Octamp\Client\Promise\PromiseInterface;
use Octamp\Client\Roles\Caller;
use Octamp\Client\Session;
use Octamp\Client\Tests\AssertCoroutine;
use Octamp\Client\Tests\Unit\TestCase;
use Thruway\Message\CallMessage;
use Thruway\Message\ErrorMessage;
use Thruway\Message\Message;
use Thruway\Message\ResultMessage;
use Thruway\Message\SubscribeMessage;

class CallerTest extends TestCase
{
    use RoleTrait;

    public static function handlesMessageProvider()
    {
        yield [new ResultMessage(1, new \stdClass()), true];
        yield [new ErrorMessage(Message::MSG_CALL, 1,  new \stdClass(), ''), true];
        yield [new CallMessage(1,  new \stdClass(), 'octamp.test'), false];
    }

    public function getRoleClass()
    {
        return Caller::class;
    }

    public function testCall()
    {
        $session = \Mockery::mock(Session::class);
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::type(CallMessage::class))
            ->once();

        $role = new Caller();
        $result = $role->call($session, 'octamp.test', [], [], []);
        $this->assertInstanceOf(ProgressablePromiseInterface::class, $result);
    }

    public function testProcessResult()
    {
        $requestId = null;
        $session = \Mockery::mock(Session::class);
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::on(function ($message) use (&$requestId) {
                if (!($message instanceof CallMessage)) {
                    return false;
                }
                $requestId = $message->getRequestId();

                return true;
            }))
            ->once();

        $assert = new AssertCoroutine();
        $caller = new Caller();
        $promise = $caller->call($session, 'octamp.test');

        $message = new ResultMessage($requestId, new \stdClass(), [2]);
        $caller->onMessage($session, $message);
        $promise->then(function ($result) use ($assert) {
            $assert->add([$this, 'assertSame'], 2, $result);
            $assert->done();
        });

        $assert->assertWait();
    }

    public function testProcessResultWithoutArgs()
    {
        $requestId = null;
        $session = \Mockery::mock(Session::class);
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::on(function ($message) use (&$requestId) {
                if (!($message instanceof CallMessage)) {
                    return false;
                }
                $requestId = $message->getRequestId();

                return true;
            }))
            ->once();

        $assert = new AssertCoroutine();
        $caller = new Caller();
        $promise = $caller->call($session, 'octamp.test');

        $message = new ResultMessage($requestId, new \stdClass());
        $caller->onMessage($session, $message);
        $promise->then(function () use ($assert) {
            $assert->add([$this, 'assertTrue'], true);
            $assert->done();
        });

        $assert->assertWait();
    }

    public function testProcessResultMultipleArgs()
    {
        $requestId = null;
        $session = \Mockery::mock(Session::class);
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::on(function ($message) use (&$requestId) {
                if (!($message instanceof CallMessage)) {
                    return false;
                }
                $requestId = $message->getRequestId();

                return true;
            }))
            ->once();

        $assert = new AssertCoroutine();
        $caller = new Caller();
        $promise = $caller->call($session, 'octamp.test');

        $message = new ResultMessage($requestId, new \stdClass(), [2, 2]);
        $caller->onMessage($session, $message);
        $promise->then(function ($result) use ($assert) {
            $assert->add([$this, 'assertSame'], [2, 2], $result->args);
            $assert->done();
        });

        $assert->assertWait();
    }

    public function testProcessResultProgress()
    {
        $requestId = null;
        $session = \Mockery::mock(Session::class);
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::on(function ($message) use (&$requestId) {
                if (!($message instanceof CallMessage)) {
                    return false;
                }
                $requestId = $message->getRequestId();

                return true;
            }))
            ->once();

        $assert = new AssertCoroutine();
        $caller = new Caller();
        $promise = $caller->call($session, 'octamp.test');

        $message = new ResultMessage($requestId, (object)['progress' => true], [50]);

        $promise->progress(function ($result) use ($assert) {
            $assert->add([$this, 'assertSame'], 50, $result);
            $assert->done();
        });

        $caller->onMessage($session, $message);

        $assert->assertWait();
    }

    public function testProcessErrorMessage()
    {
        $requestId = null;
        $session = \Mockery::mock(Session::class);
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::on(function ($message) use (&$requestId) {
                if (!($message instanceof CallMessage)) {
                    return false;
                }
                $requestId = $message->getRequestId();

                return true;
            }))
            ->once();


        $role = new Caller();
        $promise = $role->call($session, 'octamp.test');
        $message = new ErrorMessage(Message::MSG_CALL, $requestId, (object) [], '');
        $role->onMessage($session, $message);

        $assert = new AssertCoroutine();
        $promise->catch(function ($error) use ($assert) {
            $assert->add([$this, 'assertInstanceOf'], ErrorMessage::class, $error);
            $assert->add([$this, 'assertSame'], Message::MSG_ERROR, $error->getMsgCode());
            $assert->add([$this, 'assertSame'], Message::MSG_CALL, $error->getErrorMsgCode());
        });
        $assert->assert();
    }

    public function testOnMessageError()
    {
        $session = \Mockery::mock(Session::class);
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::type(ErrorMessage::class))
            ->once();

        $role = new Caller();
        $message = new SubscribeMessage(1, (object) [], 'octamp.test');
        $role->onMessage($session, $message);
    }
}
