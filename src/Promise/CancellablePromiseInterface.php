<?php

namespace Octamp\Client\Promise;

interface CancellablePromiseInterface extends PromiseInterface
{
    public function cancel(): void;
}
