<?php

namespace SWamp\Client\Promise;

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

    public static function await(callable $promise): mixed;

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

    /**
     * This method create new fulfilled promise with $value result
     *
     * @param mixed $value
     * @return PromiseInterface
     */
    public static function resolve($value): PromiseInterface;

    /**
     * This method create new rejected promise with $value result
     *
     * @param mixed $value
     * @return PromiseInterface
     */
    public static function reject($value): PromiseInterface;

    /**
     * This method create a new promise and return values when all promises are change stage
     *
     * @param PromiseInterface[] $promises
     * @return PromiseInterface
     */
    public static function all(array $promises): PromiseInterface;
}
