<?php

namespace SWamp\Client\Promise;

interface ProgressablePromiseInterface extends PromiseInterface
{
    public function then(?callable $onFulfilled = null, ?callable $onRejected = null, ?callable $onProgress = null): ProgressablePromiseInterface;

    public function progress(?callable $callback): ProgressablePromiseInterface;
}
