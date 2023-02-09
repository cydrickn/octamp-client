<?php

namespace Octamp\Client\Tests\Unit\Auth;

use Octamp\Client\Promise\ProgressPromise;
use Octamp\Client\Promise\Promise;
use Octamp\Client\Tests\AssertCoroutine;
use Octamp\Client\Tests\Unit\TestCase;

class ProgressPromiseTest extends TestCase
{
    public function testPromiseThen()
    {
        $assert = new AssertCoroutine();
        $promise = new ProgressPromise(function ($resolve, $reject, $progress) {
            usleep(100);
            $progress(40);
            $resolve('Success');
        });
        $promise->progress(function ($update) use ($assert) {
                $assert->add([$this, 'assertSame'], 40, $update);
            })->then(function ($result) use ($assert) {
                $assert->add([$this, 'assertSame'], 'Success', $result);
            }, function () {}, function () {})->wait();

        $assert->assert();
    }

    public function testRejectException()
    {
        $assert = new AssertCoroutine();

        $promise = new ProgressPromise(function () {
            throw new \Exception('Throw Rejection');
        });
        $promise->catch(function ($data) use (&$assert) {
            $assert->add([$this, 'assertInstanceOf'], \Exception::class, $data);
            $assert->add([$this, 'assertSame'], 'Throw Rejection', $data->getMessage());
        });

        $assert->assert();
    }
}
