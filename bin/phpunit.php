#!/usr/bin/env php
<?php


\Co\run(function () {
    global $argc, $argv;
    try {
        require __DIR__ . '/../vendor/bin/phpunit';
    } catch (\Swoole\ExitException $exception) {
    }
});
