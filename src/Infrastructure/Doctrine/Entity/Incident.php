<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Rattaché à une Probe et non à une Target : une Target peut avoir plusieurs
 * Probe (Epic 04) avec des états indépendants (HTTP up, Ping down par
 * exemple) ; le backlog parlait de "cible" par simplification, mais rien ne
 * définit d'agrégation multi-sondes, donc l'incident reste au niveau où
 * l'historique existe réellement : la sonde.
 */
#[ORM\Entity]
#[ORM\Table(name: 'incident')]
class Incident
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public private(set) ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Probe::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public private(set) Probe $probe;

    #[ORM\Column]
    public private(set) \DateTimeImmutable $openedAt;

    #[ORM\Column(nullable: true)]
    public private(set) ?\DateTimeImmutable $resolvedAt = null;

    private function __construct()
    {
    }

    public static function open(Probe $probe): self
    {
        $incident = new self();
        $incident->probe = $probe;
        $incident->openedAt = new \DateTimeImmutable();

        return $incident;
    }

    public function resolve(): void
    {
        $this->resolvedAt = new \DateTimeImmutable();
    }

    public function isOpen(): bool
    {
        return null === $this->resolvedAt;
    }
}
