<?php

namespace Octamp\Client\Tests;

class AssertCoroutine
{
    protected array $calls = [];
    public bool $done = false;

    public function done(): void
    {
        $this->done = true;
    }

    public function add(callable $callback, mixed ...$args): void
    {
        $this->calls[] = ['call' => $callback, 'args' => $args];
    }

    public function assert(): void
    {
        foreach ($this->calls as $call) {
            call_user_func($call['call'], ...$call['args']);
        }
    }

    public function assertWait(): void
    {
        while (!$this->done) {
        }

        $this->assert();
    }
}
