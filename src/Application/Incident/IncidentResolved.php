<?php

declare(strict_types=1);

namespace App\Application\Incident;

final readonly class IncidentResolved
{
    public function __construct(
        public int $incidentId,
        public int $probeId,
    ) {
    }
}
