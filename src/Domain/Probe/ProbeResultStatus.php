<?php

declare(strict_types=1);

namespace App\Domain\Probe;

enum ProbeResultStatus
{
    case Success;
    case Failure;
}
