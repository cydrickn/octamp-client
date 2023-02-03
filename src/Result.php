<?php

namespace SWamp\Client;

class Result
{
    public readonly array $args;
    public readonly object $kwargs;

    public function __construct(array $args, array|object $kwargs)
    {
        $this->args = $args;
        $this->kwargs = (object) $kwargs;
    }

    public function getArguments(): array
    {
        return $this->args;
    }

    public function getArgumentsKw(): object
    {
        return $this->kwargs;
    }
}
