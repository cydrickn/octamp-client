# SWamp Client
SWamp Client is an open source client for [WAMP (Web Application Messaging Protocol)](https://wamp-proto.org/), for PHP.

SWamp Client uses [Open Swoole](https://openswoole.com/docs), is a high-performance network framework based on an event-driven, asynchronous, non-blocking I/O coroutine programming model for PHP.

We also design the SWamp Client functions to be identical to [AutobahnJS](https://github.com/crossbario/autobahn-js)

The name SWamp is from Swoole + WAMP

## Supported WAMP Features

- Publish
- Subscribe
- Call
- Call Progressive
- Register

## Requierements

- PHP >= 8.1
- Swoole / OpenSwoole Extension

## Installation

```sh
composer require swamp/client
```

## Example

```php
<?php

use SWamp\Client\Auth\WampcraAuthenticator;
use SWamp\Client\Peer;
use SWamp\Client\Session;

require_once __DIR__ . '/../../vendor/autoload.php';

$client = new Peer('crossbar', 9000);

$client->onOpen(function (Session $session) {
    // subscribe
    $session->subscribe('hello', function (array $args) {
        echo 'Event ' . $args[0] . PHP_EOL;
    });

    // publish
    $session->publish('hello', ['hello swamp'], [], ['exclude_me' => false]);

    // publish with acknowledgement
    $session
        ->publish('hello', ['hello swamp with acknowledgement'], [], ['acknowledge' => true, 'exclude_me' => false])
        ->then(
            function () {
                echo 'Publish Acknowledged!' . PHP_EOL;
            },
            function ($error) {
                echo 'Publish Error ' . $error . PHP_EOL;
            },
        );

    // register
    $session->register('add', function (array $args) {
        return $args[0] + $args[1];
    });

    // call
    $session->call('add', [1, 3])->then(function ($result) {
        echo 'Result ' . $result . PHP_EOL;
    });
});

$client->open();

```

## TODOs

- Call Cancel
- Call Timeout
