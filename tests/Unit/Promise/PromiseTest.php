<?php

namespace Octamp\Client\Tests\Unit\Auth;

use Co\Channel;
use Octamp\Client\Promise\Promise;
use Octamp\Client\Tests\AssertCoroutine;
use Octamp\Client\Tests\Unit\TestCase;
use Swoole\Coroutine;
use Swoole\Event;
use Swoole\Timer;

class PromiseTest extends TestCase
{
    public function testPromiseThen()
    {
        $assert = new AssertCoroutine();
        $promise = new Promise(function ($resolve) {
            $resolve('hello octamp');
        });
        $promise->then();
        $promise->then(function ($data) use (&$assert) {
            $assert->add([$this, 'assertSame'], 'hello octamp', $data);
        });

        $assert->assert();
    }

    public function testPromiseReject()
    {
        $assert = new AssertCoroutine();

        $promise = new Promise(function ($resolve, $reject) {
            $reject('hello octamp');
        });
        $promise->then(function ()  {
        }, function ($data) use (&$assert) {
            $assert->add([$this, 'assertSame'], 'hello octamp', $data);
        });

        $assert->assert();
    }

    public function testCatch()
    {
        $assert = new AssertCoroutine();

        $promise = new Promise(function ($resolve, $reject) {
            $reject('hello octamp');
        });
        $promise->catch(function ($data) use (&$assert) {
            $assert->add([$this, 'assertSame'], 'hello octamp', $data);
        });

        $assert->assert();
    }

    public function testThenWithPromise()
    {
        $assert = new AssertCoroutine();
        $promise = new Promise(function ($resolve) {
            $resolve(new Promise(function ($resolve) {
                usleep(100);
                $resolve('hello promise result');
            }));
        });
        $promise->then(function ($data) use (&$assert) {
            $assert->add([$this, 'assertSame'], 'hello promise result', $data);
        })->wait();

        $assert->assert();
    }

    public function testRejectException()
    {
        $assert = new AssertCoroutine();

        $promise = new Promise(function ($resolve, $reject) {
            throw new \Exception('Throw Rejection');
        });
        $promise->catch(function ($data) use (&$assert) {
            $assert->add([$this, 'assertInstanceOf'], \Exception::class, $data);
            $assert->add([$this, 'assertSame'], 'Throw Rejection', $data->getMessage());
        });

        $assert->assert();
    }

    public function testRejectThenException()
    {
        $assert = new AssertCoroutine();

        $promise = new Promise(function ($resolve, $reject) {
            $resolve();
        });
        $promise
            ->then(function () use ($assert) {
                throw new \Exception('Throw Rejection');
            })
            ->catch(function ($data) use ($assert) {
                $assert->add([$this, 'assertInstanceOf'], \Exception::class, $data);
                $assert->add([$this, 'assertSame'], 'Throw Rejection', $data->getMessage());
            });

        $assert->assert();
    }

    public function testPromiseWait()
    {
        $promise = new Promise(function ($resolve) {
            usleep(100);
            $resolve('hello octamp');
        });

        $result = $promise->wait();
        $this->assertSame('hello octamp', $result);
    }
}
