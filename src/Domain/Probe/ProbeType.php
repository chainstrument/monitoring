<?php

declare(strict_types=1);

namespace App\Domain\Probe;

enum ProbeType: string
{
    case Http = 'http';
    case Ping = 'ping';
}
