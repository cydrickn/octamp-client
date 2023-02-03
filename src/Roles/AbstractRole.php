<?php

namespace SWamp\Client\Roles;

use SWamp\Client\Session;
use Thruway\Message\Message;

abstract class AbstractRole
{
    abstract public function onMessage(Session $session, Message $msg): void;

    abstract public function handlesMessage(Message $msg): bool;

    public function getFeatures(): \stdClass
    {
        return new \stdClass();
    }
}
