<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Domain\Probe\HttpProbeConfig;
use App\Domain\Probe\PingProbeConfig;
use App\Domain\Probe\ProbeType;
use Doctrine\ORM\Mapping as ORM;

/**
 * Relation Target -> Probe unidirectionnelle (pas de collection inverse sur
 * Target) : rien n'a besoin aujourd'hui de naviguer de la cible vers ses
 * sondes en mémoire ; une requête dédiée (findBy(['target' => ...])) suffit
 * le jour où ce sera nécessaire (Epic 07).
 */
#[ORM\Entity]
#[ORM\Table(name: 'probe')]
class Probe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public private(set) ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Target::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public private(set) Target $target;

    #[ORM\Column(enumType: ProbeType::class)]
    public private(set) ProbeType $type;

    /** @var array<string, mixed> */
    #[ORM\Column]
    public private(set) array $config;

    /**
     * Fréquence d'exécution de la sonde. 60s par défaut : assez réactif pour
     * un usage personnel sans bombarder les cibles surveillées.
     */
    #[ORM\Column]
    public private(set) int $intervalSeconds;

    #[ORM\Column]
    public private(set) \DateTimeImmutable $createdAt;

    private function __construct()
    {
    }

    public static function http(Target $target, HttpProbeConfig $config, int $intervalSeconds = 60): self
    {
        $probe = new self();
        $probe->target = $target;
        $probe->type = ProbeType::Http;
        $probe->config = $config->toArray();
        $probe->intervalSeconds = $intervalSeconds;
        $probe->createdAt = new \DateTimeImmutable();

        return $probe;
    }

    public static function ping(Target $target, PingProbeConfig $config, int $intervalSeconds = 60): self
    {
        $probe = new self();
        $probe->target = $target;
        $probe->type = ProbeType::Ping;
        $probe->config = $config->toArray();
        $probe->intervalSeconds = $intervalSeconds;
        $probe->createdAt = new \DateTimeImmutable();

        return $probe;
    }

    /**
     * @param ?\DateTimeImmutable $lastCheckedAt dernière exécution connue (via l'historique ProbeResult), null si jamais exécutée
     */
    public function isDueAt(?\DateTimeImmutable $lastCheckedAt, \DateTimeImmutable $now): bool
    {
        if (null === $lastCheckedAt) {
            return true;
        }

        return ($now->getTimestamp() - $lastCheckedAt->getTimestamp()) >= $this->intervalSeconds;
    }

    public function httpConfig(): HttpProbeConfig
    {
        if (ProbeType::Http !== $this->type) {
            throw new \LogicException('This probe is not an HTTP probe.');
        }

        /** @var array{url: string, expectedStatusCode: int, timeoutMs: int} $config */
        $config = $this->config;

        return HttpProbeConfig::fromArray($config);
    }

    public function pingConfig(): PingProbeConfig
    {
        if (ProbeType::Ping !== $this->type) {
            throw new \LogicException('This probe is not a Ping probe.');
        }

        /** @var array{host: string, port: int, timeoutMs: int} $config */
        $config = $this->config;

        return PingProbeConfig::fromArray($config);
    }
}
