<?php

namespace Octamp\Client\Tests\Unit\Auth;

use Co\Scheduler;
use Co\WaitGroup;
use Octamp\Client\Promise\Deferred;
use Octamp\Client\Promise\ProgressablePromiseInterface;
use Octamp\Client\Tests\AssertCoroutine;
use Octamp\Client\Tests\Unit\TestCase;
use Swoole\Coroutine;

class DeferredTest extends TestCase
{
    public function testResolve()
    {
        $deferred = new Deferred();
        $this->assertInstanceOf(ProgressablePromiseInterface::class, $deferred->promise());

        $promise = $deferred->promise();
        $deferred->resolve('hello Octamp');
        $result = $promise->wait();

        $this->assertSame('hello Octamp', $result);
    }

    public function testReject()
    {
        $deferred = new Deferred();
        $this->assertInstanceOf(ProgressablePromiseInterface::class, $deferred->promise());

        $promise = $deferred->promise();
        $deferred->reject('hello Octamp');
        $result = $promise->wait();

        $this->assertSame('hello Octamp', $result);
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
