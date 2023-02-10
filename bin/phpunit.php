#!/usr/bin/env php
<?php

$status = 0;
Co\run(function () use (&$status) {
    global $argc, $argv;
    try {
        require __DIR__ . '/../vendor/bin/phpunit';
    } catch (\Swoole\ExitException $exception) {
        $status = $exception->getStatus();
    }
});

exit($status);
