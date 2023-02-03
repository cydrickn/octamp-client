<?php

namespace SWamp\Client\Tests\Unit\Auth;

use Co\Channel;
use SWamp\Client\Promise\Promise;
use SWamp\Client\Tests\AssertCoroutine;
use SWamp\Client\Tests\Unit\TestCase;
use Swoole\Coroutine;
use Swoole\Event;
use Swoole\Timer;

class PromiseTest extends TestCase
{
    public function testPromiseThen()
    {
        $assert = new AssertCoroutine();
        $promise = new Promise(function ($resolve) {
            $resolve('hello swamp');
        });
        $promise->then();
        $promise->then(function ($data) use (&$assert) {
            $assert->add([$this, 'assertSame'], 'hello swamp', $data);
        });

        $assert->assert();
    }

    public function testPromiseReject()
    {
        $assert = new AssertCoroutine();

        $promise = new Promise(function ($resolve, $reject) {
            $reject('hello swamp');
        });
        $promise->then(function ()  {
        }, function ($data) use (&$assert) {
            $assert->add([$this, 'assertSame'], 'hello swamp', $data);
        });

        $assert->assert();
    }

    public function testCatch()
    {
        $assert = new AssertCoroutine();

        $promise = new Promise(function ($resolve, $reject) {
            $reject('hello swamp');
        });
        $promise->catch(function ($data) use (&$assert) {
            $assert->add([$this, 'assertSame'], 'hello swamp', $data);
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
            $resolve('hello swamp');
        });

        $result = $promise->wait();
        $this->assertSame('hello swamp', $result);
    }
}
