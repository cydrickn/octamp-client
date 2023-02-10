<?php

namespace Octamp\Client\Promise;

use Swoole\Coroutine;

class ProgressPromise extends Promise implements ProgressablePromiseInterface
{
    protected array $handlers = [];

    public function __construct(callable $executor)
    {
        Coroutine::create(function (callable $executor, callable $resolve, callable $reject, callable $progress) {
            try {
                $executor($resolve, $reject, $progress);
            } catch (\Throwable $exception) {
                $reject($exception);
            }
        }, $executor, [$this, 'processResolve'], [$this, 'processReject'], [$this, 'processProgress']);
    }
    public function then(?callable $onFulfilled = null, ?callable $onRejected = null, ?callable $onProgress = null): ProgressablePromiseInterface
    {
        if (is_callable($onProgress)) {
            $this->handlers[] = $onProgress;
        }
        return parent::then($onFulfilled, $onRejected);
    }

    public function progress(?callable $callback): ProgressablePromiseInterface
    {
        $this->handlers[] = $callback;

        return $this;
    }

    public function processProgress(mixed $value)
    {
        foreach ($this->handlers as $handler) {
            $handler($value);
        }
    }
}
