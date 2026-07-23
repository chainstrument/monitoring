<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Domain\Probe\ProbeResult as ProbeExecutionResult;
use App\Domain\Probe\ProbeResultStatus;
use Doctrine\ORM\Mapping as ORM;

/**
 * Historise le résultat brut d'une exécution de sonde (App\Domain\Probe\
 * ProbeResult, transitoire) pour construire l'historique et, plus tard
 * (Epic 06), détecter les incidents. L'entité est volontairement "bête" :
 * aucune logique de détection d'incident ici, seulement un enregistrement fidèle.
 */
#[ORM\Entity]
#[ORM\Table(name: 'probe_result')]
#[ORM\Index(columns: ['probe_id', 'checked_at'], name: 'idx_probe_result_probe_checked_at')]
class ProbeResult
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public private(set) ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Probe::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public private(set) Probe $probe;

    #[ORM\Column(enumType: ProbeResultStatus::class)]
    public private(set) ProbeResultStatus $status;

    #[ORM\Column(nullable: true)]
    public private(set) ?int $latencyMs;

    #[ORM\Column(nullable: true)]
    public private(set) ?string $errorMessage;

    #[ORM\Column]
    public private(set) \DateTimeImmutable $checkedAt;

    private function __construct()
    {
    }

    public static function record(Probe $probe, ProbeExecutionResult $result): self
    {
        $entity = new self();
        $entity->probe = $probe;
        $entity->status = $result->status;
        $entity->latencyMs = $result->latencyMs;
        $entity->errorMessage = $result->errorMessage;
        $entity->checkedAt = $result->checkedAt;

        return $entity;
    }
}
