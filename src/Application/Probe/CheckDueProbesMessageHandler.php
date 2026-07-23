<?php

declare(strict_types=1);

namespace App\Application\Probe;

use App\Infrastructure\Doctrine\Entity\Probe;
use App\Infrastructure\Doctrine\Entity\ProbeResult;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class CheckDueProbesMessageHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(CheckDueProbesMessage $message): void
    {
        $now = new \DateTimeImmutable();
        $lastCheckedAtByProbeId = $this->lastCheckedAtByProbeId();

        /** @var list<Probe> $probes */
        $probes = $this->entityManager->getRepository(Probe::class)->findAll();

        foreach ($probes as $probe) {
            \assert(null !== $probe->id);
            $lastCheckedAt = $lastCheckedAtByProbeId[$probe->id] ?? null;

            if ($probe->isDueAt($lastCheckedAt, $now)) {
                $this->messageBus->dispatch(new ExecuteProbeMessage($probe->id));
            }
        }
    }

    /**
     * @return array<int, \DateTimeImmutable>
     */
    private function lastCheckedAtByProbeId(): array
    {
        // MAX() sur un champ datetime n'est pas réhydraté en DateTimeImmutable
        // par Doctrine (hydratation scalaire) : on récupère une chaîne brute.
        /** @var list<array{probeId: int, lastCheckedAt: string}> $rows */
        $rows = $this->entityManager->createQuery(
            \sprintf(
                'SELECT IDENTITY(r.probe) AS probeId, MAX(r.checkedAt) AS lastCheckedAt FROM %s r GROUP BY r.probe',
                ProbeResult::class,
            ),
        )->getResult();

        $map = [];
        foreach ($rows as $row) {
            $map[$row['probeId']] = new \DateTimeImmutable($row['lastCheckedAt']);
        }

        return $map;
    }
}
