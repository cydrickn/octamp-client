#!/usr/bin/env php
<?php


$scheduler = new \Swoole\Coroutine\Scheduler();

$status = 0;
$scheduler->add(function () use (&$status) {
    global $argc, $argv;
    try {
        require __DIR__ . '/../vendor/bin/phpunit';
    } catch (\Swoole\ExitException $exception) {
        $status = $exception->getStatus();
    }
});

$scheduler->start();

exit($status);
