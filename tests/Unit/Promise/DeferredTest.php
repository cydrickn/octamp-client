<?php

namespace SWamp\Client\Tests\Unit\Auth;

use Co\Scheduler;
use Co\WaitGroup;
use SWamp\Client\Promise\Deferred;
use SWamp\Client\Promise\ProgressablePromiseInterface;
use SWamp\Client\Tests\AssertCoroutine;
use SWamp\Client\Tests\Unit\TestCase;
use Swoole\Coroutine;

class DeferredTest extends TestCase
{
    public function testResolve()
    {
        $deferred = new Deferred();
        $this->assertInstanceOf(ProgressablePromiseInterface::class, $deferred->promise());

        $promise = $deferred->promise();
        $deferred->resolve('hello swamp');
        $result = $promise->wait();

        $this->assertSame('hello swamp', $result);
    }

    public function testReject()
    {
        $deferred = new Deferred();
        $this->assertInstanceOf(ProgressablePromiseInterface::class, $deferred->promise());

        $promise = $deferred->promise();
        $deferred->reject('hello swamp');
        $result = $promise->wait();

        $this->assertSame('hello swamp', $result);
    }

    public function testProgress()
    {
        $assert = new AssertCoroutine();
        $deferred = new Deferred();
        $this->assertInstanceOf(ProgressablePromiseInterface::class, $deferred->promise());

        $promise = $deferred->promise();
        $promise->progress(function ($update) use ($assert) {
            $assert->add([$this, 'assertSame'], 100, $update);
        });
        $deferred->progress(100);
        $deferred->resolve();
        $promise->wait();

        $assert->assert();
    }
}
