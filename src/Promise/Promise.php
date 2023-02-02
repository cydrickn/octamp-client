<?php

namespace Cydrickn\SwampClient\Promise;

use Co\Channel;
use Swoole\ArrayObject;
use Swoole\Coroutine;

class Promise implements PromiseInterface
{
    const STATE_PENDING   = 1;
    const STATE_FULFILLED = 0;
    const STATE_REJECTED  = -1;

    protected mixed $result;
    protected int $state = self::STATE_PENDING;

    public function __construct(callable $executor)
    {
        $resolve = function (mixed $value = null) {
            $this->setResult($value);
            $this->setState(self::STATE_FULFILLED);
        };
        $reject  = function (mixed $value = null) {
            if ($this->isPending()) {
                $this->setResult($value);
                $this->setState(self::STATE_REJECTED);
            }
        };

        Coroutine::create(function (callable $executor, callable $resolve, callable $reject) {
            try {
                $executor($resolve, $reject);
            } catch (\Throwable $exception) {
                $reject($exception);
            }
        }, $executor, $resolve, $reject);
    }

    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): PromiseInterface
    {
        return self::create(function (callable $resolve, callable $reject) use ($onFulfilled, $onRejected) {
            while ($this->isPending()) {
                // @codeCoverageIgnoreStart
                usleep(1);
                // @codeCoverageIgnoreEnd
            }
            $callable = $this->isFulfilled() ? $onFulfilled : $onRejected;
            if (!is_callable($callable)) {
                $resolve($this->result);
                return;
            }
            try {
                $resolve($callable($this->result));
            } catch (\Throwable $error) {
                $reject($error);
            }
        });
    }

    final public function catch(callable $onRejected): PromiseInterface
    {
        return $this->then(null, $onRejected);
    }

    final public function wait(): mixed
    {
        while ($this->isPending()) {
            usleep(1);
        }

        return $this->result;
    }

    final public static function await(callable $promise): mixed
    {
        return (static::create($promise))->wait();
    }

    final public static function create(callable $promise): PromiseInterface
    {
        return new static($promise);
    }

    final public static function resolve(mixed $value): PromiseInterface
    {
        return new static(function (callable $resolve) use ($value) {
            $resolve($value);
        });
    }

    final public static function reject(mixed $value): PromiseInterface
    {
        return new static(function (callable $resolve, callable $reject) use ($value) {
            $reject($value);
        });
    }

    public static function all(array $promises): PromiseInterface
    {
        return self::create(function (callable $resolve, callable $reject) use ($promises) {
            $ticks = count($promises);

            $firstError = null;
            $channel    = new Channel($ticks);
            $result     = new ArrayObject();
            $key        = 0;
            foreach ($promises as $promise) {
                if (!$promise instanceof Promise) {
                    $channel->close();
                    throw new \RuntimeException(
                        'Supported only Streamcommon\Promise\ExtSwoolePromise instance'
                    );
                }
                $promise->then(function ($value) use ($key, $result, $channel) {
                    $result->set($key, $value);
                    $channel->push(true);
                    return $value;
                }, function ($error) use ($channel, &$firstError) {
                    $channel->push(true);
                    if ($firstError === null) {
                        $firstError = $error;
                    }
                });
                $key++;
            }
            while ($ticks--) {
                $channel->pop();
            }
            $channel->close();

            if ($firstError !== null) {
                $reject($firstError);
                return;
            }

            $resolve($result);
        });
    }

    final protected function setState(int $state): void
    {
        $this->state = $state;
    }

    final protected function isPending(): bool
    {
        return $this->state == self::STATE_PENDING;
    }

    final protected function isFulfilled(): bool
    {
        return $this->state == self::STATE_FULFILLED;
    }

    private function setResult(mixed $value): void
    {
        if ($value instanceof PromiseInterface) {
            $resolved = false;
            $callable = function ($value) use (&$resolved) {
                $this->setResult($value);
                $resolved = true;
            };
            $value->then($callable, $callable);
            // resolve async locking error
            while (!$resolved) {
                usleep(1);
            }
        } else {
            $this->result = $value;
        }
    }
}
