<?php

namespace Octamp\Client\Tests\Unit\Roles;

use Octamp\Client\Promise\ProgressablePromiseInterface;
use Octamp\Client\Roles\Subscriber;
use Octamp\Client\Session;
use Octamp\Client\Tests\Unit\TestCase;
use Thruway\Message\CallMessage;
use Thruway\Message\ErrorMessage;
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
}
