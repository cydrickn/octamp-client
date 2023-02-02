<?php


function await(mixed $callback): mixed
{
    return (new \Cydrickn\SwampClient\Promise\Promise($callback))->wait();
}

function promise(callable $callback): \Cydrickn\SwampClient\Promise\Promise
{
    return new \Cydrickn\SwampClient\Promise\Promise($callback);
}
