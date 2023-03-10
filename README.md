# Octamp Client


[![Packagist PHP Version](https://img.shields.io/packagist/dependency-v/octamp/client/php?style=flat-square)](https://packagist.org/packages/octamp/client)
[![License](https://img.shields.io/packagist/l/octamp/client?style=flat-square)](/LICENSE)
[![Test](https://github.com/cydrickn/octamp-client/actions/workflows/test.yml/badge.svg?branch=main)](https://github.com/cydrickn/octamp-client/actions/workflows/test.yml)
[![codecov](https://codecov.io/github/cydrickn/octamp-client/branch/main/graph/badge.svg?token=8Y8BNJE1VG)](https://codecov.io/github/cydrickn/octamp-client)

Octamp Client is an open source client for [WAMP (Web Application Messaging Protocol)](https://wamp-proto.org/), for PHP.

Octamp Client uses [Open Swoole](https://openswoole.com/docs), is a high-performance network framework based on an event-driven, asynchronous, non-blocking I/O coroutine programming model for PHP.

We also design the Octamp Client functions to be identical to [AutobahnJS](https://github.com/crossbario/autobahn-js)

The name Octamp is from Octopus + WAMP

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
composer require octamp/client
```

## Example

```php
<?php

use Octamp\Client\Auth\WampcraAuthenticator;
use Octamp\Client\Peer;
use Octamp\Client\Session;

require_once __DIR__ . '/../../vendor/autoload.php';

\Co\run(function () {
    $client = new Peer('crossbar', 9000);
    
    $client->onOpen(function (Session $session) {
        // subscribe
        $session->subscribe('hello', function (array $args) {
            echo 'Event ' . $args[0] . PHP_EOL;
        });
    
        // publish
        $session->publish('hello', ['hello octamp'], [], ['exclude_me' => false]);
    
        // publish with acknowledgement
        $session
            ->publish('hello', ['hello octamp with acknowledgement'], [], ['acknowledge' => true, 'exclude_me' => false])
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
});

```

## TODOs

- Call Cancel
- Call Timeout
- Unsubscribe
- RPC Progress Call
- Auto Reconnect
- Subprotocol Handling
- Heartbeat
- Custom Error Handling
- TLS connection
- Session Logging
- Session Prefix
- Pattern Base Subscription / Registration
