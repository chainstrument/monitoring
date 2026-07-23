<?php

declare(strict_types=1);

namespace App\Domain\Probe;

enum ProbeResultStatus: string
{
    case Success = 'success';
    case Failure = 'failure';
}
