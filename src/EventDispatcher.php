<?php

namespace Octamp\Client;

use Swoole\Coroutine;

class EventDispatcher
{
    protected array $events = [];

    public function emit(string $eventName, ...$args): void
    {
        Coroutine::create(function () use ($eventName, $args) {
            $events = $this->events[$eventName] ?? [];
            if (empty($events)) {
                return;
            }

            foreach ($events as $event) {
                call_user_func($event, ...$args);
            }
        });
    }

    public function on($event, $callback): void
    {
        if (!isset($this->events[$event])) {
            $this->events[$event] = [];
        }

        $this->events[$event][] = $callback;
    }

    public function off(string $event, callable $callback): void
    {
        $events = $this->getEvents($event);
        if (empty($events)) {
            return;
        }

        $index = array_search($callback, $events);
        array_splice($this->events[$event], $index, 1);
    }

    public function getEvents(?string $name = null): array
    {
        if ($name === null) {
            return $this->events;
        }

        return $this->events[$name] ?? [];
    }
}
