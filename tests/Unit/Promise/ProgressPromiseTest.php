<?php

namespace SWamp\Client\Tests\Unit\Auth;

use SWamp\Client\Promise\ProgressPromise;
use SWamp\Client\Promise\Promise;
use SWamp\Client\Tests\AssertCoroutine;
use SWamp\Client\Tests\Unit\TestCase;

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
