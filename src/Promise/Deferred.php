<?php

namespace Octamp\Client\Promise;

class Deferred
{
    private ?ProgressPromise $promise = null;
    private mixed $resolveCallback;
    private mixed $rejectCallback;
    private mixed $progressCallback;

    public function promise(): ProgressPromise
    {
        if ($this->promise === null) {
            $this->promise = new ProgressPromise(function ($resolve, $reject, $progress) {
                $this->resolveCallback = $resolve;
                $this->rejectCallback = $reject;
                $this->progressCallback = $progress;
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

    public function progress(mixed $progress): void
    {
        $this->promise();
        call_user_func($this->progressCallback, $progress);
    }
}
