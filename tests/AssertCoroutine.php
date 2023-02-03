<?php

namespace SWamp\Client\Tests;

class AssertCoroutine
{
    protected array $calls = [];

    public function add(callable $callback, mixed ...$args)
    {
        $this->calls[] = ['call' => $callback, 'args' => $args];
    }

    public function assert()
    {
        foreach ($this->calls as $call) {
            call_user_func($call['call'], ...$call['args']);
        }
    }
}
