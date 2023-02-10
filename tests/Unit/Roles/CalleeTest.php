<?php

namespace Octamp\Client\Tests\Unit\Roles;

use Octamp\Client\Promise\ProgressablePromiseInterface;
use Octamp\Client\Promise\ProgressPromise;
use Octamp\Client\Promise\Promise;
use Octamp\Client\Result;
use Octamp\Client\Roles\Callee;
use Octamp\Client\Session;
use Octamp\Client\Tests\AssertCoroutine;
use Octamp\Client\Tests\Unit\TestCase;
use Swoole\Coroutine;
use Thruway\Message\ErrorMessage;
use Thruway\Message\EventMessage;
use Thruway\Message\InvocationMessage;
use Thruway\Message\Message;
use Thruway\Message\RegisteredMessage;
use Thruway\Message\RegisterMessage;
use Thruway\Message\SubscribedMessage;
use Thruway\Message\UnregisteredMessage;
use Thruway\Message\UnregisterMessage;
use Thruway\Message\YieldMessage;
use Thruway\WampErrorException;

class CalleeTest extends TestCase
{
    use RoleTrait;

    public static function handlesMessageProvider(): \Generator
    {
        yield [new RegisteredMessage(1, 1), true];
        yield [new ErrorMessage(Message::MSG_REGISTERED, 1,  new \stdClass(), ''), true];
        yield [new EventMessage(1,  1, new \stdClass()), false];
    }

    public function getRoleClass()
    {
        return Callee::class;
    }

    public function testRegister()
    {
        $session = \Mockery::mock(Session::class);
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::type(RegisterMessage::class))
            ->once();

        $role = new Callee();
        $result = $role->register($session, 'octamp.test', function () {});
        $this->assertInstanceOf(ProgressablePromiseInterface::class, $result);
    }

    public function testUnregister()
    {
        $session = \Mockery::mock(Session::class);
        $requestId = null;
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::on(function ($message) use (&$requestId) {
                if (!($message instanceof RegisterMessage)) {
                    return false;
                }
                $requestId = $message->getRequestId();

                return true;
            }))
            ->once();
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::type(UnregisterMessage::class))
            ->once();

        $role = new Callee();
        $role->register($session, 'octamp.test', function () {});
        $registered = new RegisteredMessage($requestId, 2);
        $role->onMessage($session, $registered);
        $promise = $role->unregister($session, 'octamp.test');
        $this->assertInstanceOf(ProgressablePromiseInterface::class, $promise);
    }

    public function testUnregisterReject()
    {
        $session = \Mockery::mock(Session::class);
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::type(RegisterMessage::class))
            ->once();
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::type(UnregisterMessage::class))
            ->once();

        $role = new Callee();
        $promise = $role->register($session, 'octamp.test', function () {});
        $role->unregister($session, 'octamp.test');

        $assert = new AssertCoroutine();
        $promise->catch(function ($error) use($assert) {
            $assert->add([$this, 'assertEquals'], 'Registration ID is not set while attempting to unregister octamp.test', $error);
            $assert->done();
        });

        $assert->assertWait();
    }

    public function testUnregisterFalse()
    {
        $session = \Mockery::mock(Session::class);
        $role = new Callee();
        $result = $role->unregister($session, 'octamp.test');
        $this->assertFalse($result);
    }

    public function testProcessRegistered()
    {
        $session = \Mockery::mock(Session::class);
        $requestId = null;
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::on(function ($message) use (&$requestId) {
                if (!($message instanceof RegisterMessage)) {
                    return false;
                }
                $requestId = $message->getRequestId();

                return true;
            }))
            ->once();

        $role = new Callee();
        $promise = $role->register($session, 'octamp.test', function () {});

        $registered = new RegisteredMessage($requestId, 2);
        $assert = new AssertCoroutine();
        $role->onMessage($session, $registered);

        $promise->then(function ($message) use ($assert, $requestId) {
            $assert->add([$this, 'assertInstanceOf'], RegisteredMessage::class, $message);
            $assert->add([$this, 'assertSame'], 2, $message->getRegistrationId());
            $assert->add([$this, 'assertSame'], $requestId, $message->getRequestId());
            $assert->done();
        });

        $assert->assertWait();
    }

    public function testProcessUnregistered()
    {
        $session = \Mockery::mock(Session::class);
        $requestId = null;
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::on(function ($message) use (&$requestId) {
                if (!($message instanceof RegisterMessage)) {
                    return false;
                }
                $requestId = $message->getRequestId();

                return true;
            }))
            ->once();
        $unregisterRequestId = null;
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::on(function ($message) use (&$unregisterRequestId) {
                if (!($message instanceof UnregisterMessage)) {
                    return false;
                }
                $unregisterRequestId = $message->getRequestId();

                return true;
            }))
            ->once();

        $role = new Callee();
        $role->register($session, 'octamp.test', function () {});
        $registered = new RegisteredMessage($requestId, 2);
        $role->onMessage($session, $registered);
        $promise = $role->unregister($session, 'octamp.test');
        $unregistered = new UnregisteredMessage($unregisterRequestId);
        $role->onMessage($session, $unregistered);

        $assert = new AssertCoroutine();
        $promise->then(function () use ($assert) {
            $assert->done();
        });
        $assert->assertWait();
    }

    public function testProcessInvocationErrorNoCallback()
    {
        $session = \Mockery::mock(Session::class);
        $requestId = null;
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::on(function ($message) use (&$requestId) {
                if (!($message instanceof RegisterMessage)) {
                    return false;
                }
                $requestId = $message->getRequestId();

                return true;
            }))
            ->once();
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::type(UnregisterMessage::class))
            ->once();

        $session->shouldReceive('sendMessage')
            ->with(\Mockery::type(ErrorMessage::class))
            ->once();

        $role = new Callee();
        $role->register($session, 'octamp.test', function () {});
        $registered = new RegisteredMessage($requestId, 2);
        $role->onMessage($session, $registered);
        $role->unregister($session, 'octamp.test');

        $invocation = new InvocationMessage(3, 2, (object)[]);
        $role->onMessage($session, $invocation);
    }

    public function testProcessInvocationWampException()
    {
        $session = \Mockery::mock(Session::class);
        $requestId = null;
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::on(function ($message) use (&$requestId) {
                if (!($message instanceof RegisterMessage)) {
                    return false;
                }
                $requestId = $message->getRequestId();

                return true;
            }))
            ->once();

        $session->shouldReceive('sendMessage')
            ->with(\Mockery::type(ErrorMessage::class))
            ->once();

        $role = new Callee();
        $role->register($session, 'octamp.test', function () {
            throw new WampErrorException('octamp.test', [], []);
        });
        $registered = new RegisteredMessage($requestId, 2);
        $role->onMessage($session, $registered);

        $invocation = new InvocationMessage(3, 2, (object)[]);
        $role->onMessage($session, $invocation);
    }

    public function testProcessInvocationException()
    {
        $session = \Mockery::mock(Session::class);
        $requestId = null;
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::on(function ($message) use (&$requestId) {
                if (!($message instanceof RegisterMessage)) {
                    return false;
                }
                $requestId = $message->getRequestId();

                return true;
            }))
            ->once();

        $session->shouldReceive('sendMessage')
            ->with(\Mockery::type(ErrorMessage::class))
            ->once();

        $role = new Callee();
        $role->register($session, 'octamp.test', function () {
            throw new \Exception('Test Exception');
        });
        $registered = new RegisteredMessage($requestId, 2);
        $role->onMessage($session, $registered);

        $invocation = new InvocationMessage(3, 2, (object)[]);
        $role->onMessage($session, $invocation);
    }

    /**
     * @dataProvider processInvocationProvider
     */
    public function testProcessInvocation($callResult, $expectedResult)
    {
        $session = \Mockery::mock(Session::class);
        $requestId = null;
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::on(function ($message) use (&$requestId) {
                if (!($message instanceof RegisterMessage)) {
                    return false;
                }
                $requestId = $message->getRequestId();

                return true;
            }))
            ->once();

        $assert = new AssertCoroutine();
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::on(function ($message) use ($assert, $expectedResult) {
                $assert->add([$this, 'assertInstanceOf'], YieldMessage::class, $message);
                if (!($message instanceof YieldMessage)) {
                    return false;
                }
                $assert->add([$this, 'assertSame'], $expectedResult, $message->getArguments());
                $assert->done();

                return true;
            }))
            ->once();

        $role = new Callee();
        $role->register($session, 'octamp.test', function () use ($callResult) {
            return $callResult;
        });
        $registered = new RegisteredMessage($requestId, 2);
        $role->onMessage($session, $registered);

        $invocation = new InvocationMessage(3, 2, (object)[]);
        $role->onMessage($session, $invocation);

        $assert->assertWait();
    }

    public static function processInvocationProvider()
    {
        yield [2, [2]];
        yield [new Result([3]), [3]];
        yield [new Promise(function ($resolve) {
            $resolve('test promise');
        }), ['test promise']];
        yield [new Promise(function ($resolve) {
            $resolve(new Result(['test promise with result']));
        }), ['test promise with result']];
    }

    public function testProcessInvocationError()
    {
        $session = \Mockery::mock(Session::class);
        $requestId = null;
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::on(function ($message) use (&$requestId) {
                if (!($message instanceof RegisterMessage)) {
                    return false;
                }
                $requestId = $message->getRequestId();

                return true;
            }))
            ->once();

        $assert = new AssertCoroutine();
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::on(function ($message) use ($assert) {
                $assert->add([$this, 'assertInstanceOf'], ErrorMessage::class, $message);
                if (!($message instanceof ErrorMessage)) {
                    return false;
                }
                $assert->add([$this, 'assertSame'], 'octamp.test.error', $message->getErrorURI());
                $assert->done();

                return true;
            }))
            ->once();

        $role = new Callee();
        $role->register($session, 'octamp.test', function () {
            return new Promise(function ($resolve, $reject) {
                $reject('Test Error');
            });
        });
        $registered = new RegisteredMessage($requestId, 2);
        $role->onMessage($session, $registered);

        $invocation = new InvocationMessage(3, 2, (object)[]);
        $role->onMessage($session, $invocation);

        $assert->assertWait();
    }

    public function testProcessInvocationResultException()
    {
        $session = \Mockery::mock(Session::class);
        $requestId = null;
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::on(function ($message) use (&$requestId) {
                if (!($message instanceof RegisterMessage)) {
                    return false;
                }
                $requestId = $message->getRequestId();

                return true;
            }))
            ->once();

        $assert = new AssertCoroutine();
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::on(function ($message) use ($assert) {
                $assert->add([$this, 'assertInstanceOf'], ErrorMessage::class, $message);
                if (!($message instanceof ErrorMessage)) {
                    $assert->done();
                    return false;
                }
                $assert->add([$this, 'assertSame'], 'octamp.test.error', $message->getErrorURI());
                $assert->done();

                return true;
            }))
            ->once();

        $role = new Callee();
        $role->register($session, 'octamp.test', function () {
            return new Promise(function () {
                throw new \Exception('test error');
            });
        });
        $registered = new RegisteredMessage($requestId, 2);
        $role->onMessage($session, $registered);

        $invocation = new InvocationMessage(3, 2, (object)[]);
        $role->onMessage($session, $invocation);

        $assert->assertWait();
    }

    public function testProcessErrorMessageRegister()
    {
        $requestId = null;
        $session = \Mockery::mock(Session::class);
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::on(function ($message) use (&$requestId) {
                if (!($message instanceof RegisterMessage)) {
                    return false;
                }
                $requestId = $message->getRequestId();

                return true;
            }))
            ->once();


        $role = new Callee();
        $promise = $role->register($session, 'octamp.test', function () {});
        $message = new ErrorMessage(Message::MSG_REGISTER, $requestId, (object) [], '');
        $role->onMessage($session, $message);

        $assert = new AssertCoroutine();
        $promise->catch(function ($error) use ($assert) {
            $assert->add([$this, 'assertInstanceOf'], ErrorMessage::class, $error);
            $assert->add([$this, 'assertSame'], Message::MSG_ERROR, $error->getMsgCode());
            $assert->add([$this, 'assertSame'], Message::MSG_REGISTER, $error->getErrorMsgCode());
            $assert->done();
        });
        $assert->assertWait();
    }

    public function testProcessErrorMessageUnregister()
    {
        $session = \Mockery::mock(Session::class);
        $requestId = null;
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::on(function ($message) use (&$requestId) {
                if (!($message instanceof RegisterMessage)) {
                    return false;
                }
                $requestId = $message->getRequestId();

                return true;
            }))
            ->once();
        $unregisterRequestId = null;
        $session->shouldReceive('sendMessage')
            ->with(\Mockery::on(function ($message) use (&$unregisterRequestId) {
                if (!($message instanceof UnregisterMessage)) {
                    return false;
                }
                $unregisterRequestId = $message->getRequestId();

                return true;
            }))
            ->once();

        $role = new Callee();
        $role->register($session, 'octamp.test', function () {});
        $registered = new RegisteredMessage($requestId, 2);
        $role->onMessage($session, $registered);
        $promise = $role->unregister($session, 'octamp.test');
        $message = new ErrorMessage(Message::MSG_UNREGISTER, $unregisterRequestId, (object) [], '');
        $role->onMessage($session, $message);

        $assert = new AssertCoroutine();
        $promise->catch(function ($error) use ($assert) {
            $assert->add([$this, 'assertInstanceOf'], ErrorMessage::class, $error);
            $assert->add([$this, 'assertSame'], Message::MSG_ERROR, $error->getMsgCode());
            $assert->add([$this, 'assertSame'], Message::MSG_UNREGISTER, $error->getErrorMsgCode());
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

        $role = new Callee();
        $message = new SubscribedMessage(1, (object) [], 'octamp.test');
        $role->onMessage($session, $message);
    }
}
