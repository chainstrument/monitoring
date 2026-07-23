<?php

declare(strict_types=1);

namespace App\Application\Probe;

final readonly class ExecuteProbeMessage
{
    public function __construct(public int $probeId)
    {
    }
}
