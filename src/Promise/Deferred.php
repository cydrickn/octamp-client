<?php

namespace Cydrickn\SwampClient\Promise;

class Deferred
{
    private ?PromiseInterface $promise = null;
    private mixed $resolveCallback;
    private mixed $rejectCallback;

    public function promise(): PromiseInterface
    {
        if ($this->promise === null) {
            $this->promise = new Promise(function ($resolve, $reject) {
                $this->resolveCallback = $resolve;
                $this->rejectCallback = $reject;
            });
        }

        return $this->promise;
    }

    public function resolve(mixed $value = null): void
    {
        $this->promise();
        call_user_func($this->resolveCallback, $value);
    }

    public function reject(mixed $reason): void
    {
        $this->promise();
        call_user_func($this->rejectCallback, $reason);
    }
}
