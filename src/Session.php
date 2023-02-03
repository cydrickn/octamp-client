<?php

namespace SWamp\Client;

use SWamp\Client\Promise\PromiseInterface;
use Thruway\Message\Message;

class Session
{
    const STATE_UNKNOWN = 0;
    const STATE_PRE_HELLO = 1;
    const STATE_CHALLENGE_SENT = 2;
    const STATE_UP = 3;
    const STATE_DOWN = 4;

    protected int $sessionId;
    protected int $state;

    public function __construct(protected Peer $peer)
    {
    }

    public function subscribe(string $topicName, callable $callback, array|object $options = [])
    {
        return $this->peer->getSubscriber()->subscribe($this, $topicName, $callback, $options);
    }

    public function publish(string $topicName, array $args = [], array|object $kwargs = [], array|object $options = []): ?PromiseInterface
    {
        return $this->peer->getPublisher()->publish($this, $topicName, $args, $kwargs, $options);
    }

    public function call(string $topicName, array $args = [], array|object $kwargs = [], array|object $options = []): PromiseInterface
    {
        return $this->peer->getCaller()->call($this, $topicName, $args, $kwargs, $options);
    }

    public function register(string $procedureName, callable $callback, array|object $options = []): PromiseInterface
    {
        return $this->peer->getCallee()->register($this, $procedureName, $callback, $options);
    }

    public function unregister(string $uri): PromiseInterface
    {
        return $this->peer->getCallee()->unregister($this, $uri);
    }

    public function setSessionId(int $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    public function setState(int $state): void
    {
        $this->state = $state;
    }

    public function sendMessage(Message $message)
    {
        $this->peer->sendMessage($message);
    }
}
