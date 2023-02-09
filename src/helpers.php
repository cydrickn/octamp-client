<?php


function await(mixed $callback): mixed
{
    return (new \Octamp\Client\Promise\Promise($callback))->wait();
}

function promise(callable $callback): \Octamp\Client\Promise\Promise
{
    return new \Octamp\Client\Promise\Promise($callback);
}
