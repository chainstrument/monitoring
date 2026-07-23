<?php

declare(strict_types=1);

namespace App\Domain\Probe;

/**
 * Résultat brut de l'exécution d'une sonde, à l'instant où elle a tourné.
 * Ne persiste rien : la construction d'un historique (entité ProbeResult
 * Doctrine, détection d'incidents) est le rôle de l'Epic 06, pas de celui-ci.
 */
final readonly class ProbeResult
{
    private function __construct(
        public ProbeResultStatus $status,
        public ?int $latencyMs,
        public ?string $errorMessage,
        public \DateTimeImmutable $checkedAt,
    ) {
    }

    public static function success(int $latencyMs): self
    {
        return new self(ProbeResultStatus::Success, $latencyMs, null, new \DateTimeImmutable());
    }

    public static function failure(string $errorMessage, ?int $latencyMs = null): self
    {
        return new self(ProbeResultStatus::Failure, $latencyMs, $errorMessage, new \DateTimeImmutable());
    }

    public function isSuccess(): bool
    {
        return ProbeResultStatus::Success === $this->status;
    }
}
