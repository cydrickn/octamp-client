<?php

namespace SWamp\Client;

use Swoole\Coroutine;

class EventDispatcher
{
    protected array $events = [];

    public function emit(string $eventName, ...$args): void
    {
        Coroutine\go(function () use ($eventName, $args) {
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
}
