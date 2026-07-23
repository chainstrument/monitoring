<?php

declare(strict_types=1);

namespace App\Domain\Incident;

enum ProbeState
{
    case Up;
    case Down;
}
