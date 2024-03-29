<?php

namespace Octamp\Client;

use Octamp\Client\Promise\PromiseInterface;
use Thruway\Message\Message;

class Session
{
    const STATE_UNKNOWN = 0;
    const STATE_PRE_HELLO = 1;
    const STATE_CHALLENGE_SENT = 2;
    const STATE_UP = 3;
    const STATE_DOWN = 4;

    protected ?int $sessionId;
    protected int $state;
    protected object $details;

    protected bool $goodbyeSent = false;

    public function __construct(protected Peer $peer)
    {
        $this->state = self::STATE_UNKNOWN;
        $this->sessionId = null;
        $this->details = (object) [];
    }

    public function subscribe(string $topicName, callable $callback, array|object $options = []): PromiseInterface
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

    public function getSessionId(): ?int
    {
        return $this->sessionId;
    }

    public function getId(): ?int
    {
        return $this->sessionId;
    }

    public function setState(int $state): void
    {
        $this->state = $state;
    }

    public function getState(): int
    {
        return $this->state;
    }

    public function sendMessage(Message $message): void
    {
        $this->peer->sendMessage($message);
    }

    public function setDetails(array|object $details): void
    {
        $this->details = (object) $details;
    }

    public function getRealm(): ?string
    {
        return $this->details->realm ?? null;
    }

    public function getFeatures(): ?object
    {
        return $this->details->roles ?? (object) [];
    }

    public function getSubscriptions(): array
    {
        return $this->peer->getSubscriber()->getSubscriptions();
    }

    public function getRegistrations(): array
    {
        return $this->peer->getCallee()->getRegistrations();
    }

    public function setGoodByeSent(bool $goodByeSent): void
    {
        $this->goodbyeSent = $goodByeSent;
    }

    public function isGoodByeSent(): bool
    {
        return $this->goodbyeSent;
    }
}
