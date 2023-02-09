<?php

namespace Octamp\Client\Promise;

interface PromiseInterface
{
    /**
     * It be called after promise change stage
     *
     * @param callable|null $onFulfilled called after promise is fulfilled
     * @param callable|null $onRejected  called after promise is rejected
     * @return PromiseInterface
     *
     * @see https://promisesaplus.com/#the-then-method
     */
    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): PromiseInterface;

    public function wait(): mixed;

    /**
     * This method return a promise with rejected case only
     *
     * @param callable $onRejected
     * @return PromiseInterface
     */
    public function catch(callable $onRejected): PromiseInterface;

    /**
     * This method create new promise instance
     *
     * @param callable $promise
     * @return PromiseInterface
     */
    public static function create(callable $promise): PromiseInterface;
}
