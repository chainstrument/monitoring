<?php

declare(strict_types=1);

namespace App\Application\Probe;

use App\Application\Incident\IncidentStateUpdater;
use App\Domain\Probe\ProbeExecutorInterface;
use App\Domain\Probe\ProbeType;
use App\Infrastructure\Doctrine\Entity\Probe;
use App\Infrastructure\Doctrine\Entity\ProbeResult;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ExecuteProbeMessageHandler
{
    /**
     * @param iterable<ProbeExecutorInterface> $executors
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        #[AutowireIterator('app.probe_executor')]
        private iterable $executors,
        private IncidentStateUpdater $incidentStateUpdater,
    ) {
    }

    public function __invoke(ExecuteProbeMessage $message): void
    {
        $probe = $this->entityManager->find(Probe::class, $message->probeId);

        if (null === $probe) {
            // Erreur technique (la sonde a disparu entre le dispatch et l'exécution) :
            // on laisse Messenger retenter selon la politique de retry (US-05.3).
            throw new \RuntimeException(\sprintf('Probe #%d not found.', $message->probeId));
        }

        $result = $this->resolveExecutor($probe->type)->execute($probe);

        $this->entityManager->persist(ProbeResult::record($probe, $result));
        $this->entityManager->flush();

        $this->incidentStateUpdater->updateFor($probe);
    }

    private function resolveExecutor(ProbeType $type): ProbeExecutorInterface
    {
        foreach ($this->executors as $executor) {
            if ($executor->supports($type)) {
                return $executor;
            }
        }

        throw new \LogicException(\sprintf('No executor supports probe type "%s".', $type->value));
    }
}
