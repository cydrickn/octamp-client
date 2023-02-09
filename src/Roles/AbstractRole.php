<?php

namespace Octamp\Client\Roles;

use Octamp\Client\Session;
use Thruway\Message\Message;

abstract class AbstractRole
{
    abstract public function onMessage(Session $session, Message $msg): void;

    abstract public function handlesMessage(Message $msg): bool;

    abstract public function getFeatures(): \stdClass;
}
