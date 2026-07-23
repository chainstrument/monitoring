<?php

declare(strict_types=1);

namespace App\Application\Incident;

use App\Domain\Incident\IncidentDetector;
use App\Domain\Incident\ProbeState;
use App\Domain\Probe\ProbeResultStatus;
use App\Infrastructure\Doctrine\Entity\Incident;
use App\Infrastructure\Doctrine\Entity\Probe;
use App\Infrastructure\Doctrine\Entity\ProbeResult;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Fait le lien entre l'exécution d'une sonde (ExecuteProbeMessageHandler,
 * Epic 05) et la détection d'incident (IncidentDetector, pure) : recharge
 * l'historique récent, réévalue l'état, ouvre/résout un Incident si l'état a
 * changé, et dispatche l'événement métier correspondant.
 */
final readonly class IncidentStateUpdater
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private IncidentDetector $incidentDetector,
        #[Autowire(service: 'event.bus')]
        private MessageBusInterface $eventBus,
    ) {
    }

    public function updateFor(Probe $probe): void
    {
        $state = $this->incidentDetector->detect($this->recentStatusesNewestFirst($probe));
        $openIncident = $this->entityManager->getRepository(Incident::class)
            ->findOneBy(['probe' => $probe, 'resolvedAt' => null]);

        if (ProbeState::Down === $state && null === $openIncident) {
            $incident = Incident::open($probe);
            $this->entityManager->persist($incident);
            $this->entityManager->flush();
            \assert(null !== $incident->id && null !== $probe->id);
            $this->eventBus->dispatch(new IncidentOpened($incident->id, $probe->id));

            return;
        }

        if (ProbeState::Up === $state && null !== $openIncident) {
            $openIncident->resolve();
            $this->entityManager->flush();
            \assert(null !== $openIncident->id && null !== $probe->id);
            $this->eventBus->dispatch(new IncidentResolved($openIncident->id, $probe->id));
        }
    }

    /**
     * @return list<ProbeResultStatus>
     */
    private function recentStatusesNewestFirst(Probe $probe): array
    {
        // checkedAt est stocké à la seconde près (TIMESTAMP(0)) : plusieurs
        // résultats créés dans la même seconde ont le même checkedAt, d'où le
        // tri secondaire par id pour un ordre "plus récent d'abord" déterministe.
        $results = $this->entityManager->getRepository(ProbeResult::class)->findBy(
            ['probe' => $probe],
            ['checkedAt' => 'DESC', 'id' => 'DESC'],
            $this->incidentDetector->consecutiveFailureThreshold,
        );

        return array_map(static fn (ProbeResult $result): ProbeResultStatus => $result->status, $results);
    }
}
