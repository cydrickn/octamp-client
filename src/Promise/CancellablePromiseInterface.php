<?php

namespace SWamp\Client\Promise;

interface CancellablePromiseInterface extends PromiseInterface
{
    public function cancel(): void;
}
