<?php

declare(strict_types=1);

namespace App\Application\Target;

use App\Domain\Probe\ProbeResultStatus;
use App\Domain\Target\TargetType;

/**
 * Read model pour le dashboard (US-07.1) : pas une entité, juste la
 * projection d'une ligne de TargetDashboardQuery. Le statut affiché agrège
 * l'ensemble des sondes de la cible (voir Epic 06 : l'Incident est par
 * sonde, pas par cible) — DOWN si au moins une sonde a un incident ouvert.
 */
final readonly class TargetSummary
{
    public function __construct(
        public int $id,
        public string $name,
        public TargetType $type,
        public string $identifier,
        /** @var list<string> */
        public array $tags,
        public ?ProbeResultStatus $lastStatus,
        public ?int $lastLatencyMs,
        public ?\DateTimeImmutable $lastCheckedAt,
        public bool $hasOpenIncident,
    ) {
    }

    public function displayStatus(): string
    {
        if (null === $this->lastStatus) {
            return 'unknown';
        }

        return $this->hasOpenIncident ? 'down' : 'up';
    }
}
