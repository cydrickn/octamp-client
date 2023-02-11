<?php

declare(strict_types=1);

namespace Octamp\Client\Enum;

enum Close: string
{
    case closed = 'closed';
    case lost = 'lost';
    case unreachable = 'unreachable';
    case unsupported = 'unsupported';
}
