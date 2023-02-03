<?php


function await(mixed $callback): mixed
{
    return (new \SWamp\Client\Promise\Promise($callback))->wait();
}

function promise(callable $callback): \SWamp\Client\Promise\Promise
{
    return new \SWamp\Client\Promise\Promise($callback);
}
