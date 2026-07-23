<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Incident;

use App\Application\Incident\IncidentOpened;
use App\Application\Incident\IncidentResolved;
use App\Application\Incident\IncidentStateUpdater;
use App\Domain\Incident\IncidentDetector;
use App\Domain\Probe\HttpProbeConfig;
use App\Domain\Probe\ProbeResult as ProbeExecutionResult;
use App\Domain\Shared\Url;
use App\Domain\Target\TargetType;
use App\Infrastructure\Doctrine\Entity\Incident;
use App\Infrastructure\Doctrine\Entity\Probe;
use App\Infrastructure\Doctrine\Entity\ProbeResult;
use App\Infrastructure\Doctrine\Entity\Target;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class IncidentStateUpdaterTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testFirstFailureDoesNotOpenAnIncidentYet(): void
    {
        $probe = $this->createProbeWithResults([false]);

        $eventBus = $this->createMock(MessageBusInterface::class);
        $eventBus->expects(self::never())->method('dispatch');

        new IncidentStateUpdater($this->entityManager, new IncidentDetector(3), $eventBus)->updateFor($probe);

        self::assertNull($this->entityManager->getRepository(Incident::class)->findOneBy(['probe' => $probe]));
    }

    public function testThreeConsecutiveFailuresOpenAnIncidentAndDispatchTheEvent(): void
    {
        $probe = $this->createProbeWithResults([false, false, false]);

        $eventBus = $this->createMock(MessageBusInterface::class);
        $eventBus->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(IncidentOpened::class))
            ->willReturn(new Envelope(new \stdClass()));

        new IncidentStateUpdater($this->entityManager, new IncidentDetector(3), $eventBus)->updateFor($probe);

        $incident = $this->entityManager->getRepository(Incident::class)->findOneBy(['probe' => $probe]);
        self::assertNotNull($incident);
        self::assertTrue($incident->isOpen());
    }

    public function testARecoveryResolvesTheOpenIncidentAndDispatchesTheEvent(): void
    {
        $probe = $this->createProbeWithResults([false, false, false]);
        $noopBus = $this->createStub(MessageBusInterface::class);
        $noopBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));
        new IncidentStateUpdater($this->entityManager, new IncidentDetector(3), $noopBus)->updateFor($probe);

        $this->recordResult($probe, true);

        $eventBus = $this->createMock(MessageBusInterface::class);
        $eventBus->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(IncidentResolved::class))
            ->willReturn(new Envelope(new \stdClass()));

        new IncidentStateUpdater($this->entityManager, new IncidentDetector(3), $eventBus)->updateFor($probe);

        $incident = $this->entityManager->getRepository(Incident::class)->findOneBy(['probe' => $probe]);
        self::assertNotNull($incident);
        self::assertFalse($incident->isOpen());
    }

    /**
     * @param list<bool> $successes
     */
    private function createProbeWithResults(array $successes): Probe
    {
        $target = Target::create('Cible pour les incidents', TargetType::Website, 'https://example.com');
        $probe = Probe::http($target, new HttpProbeConfig(new Url('https://example.com')));
        $this->entityManager->persist($target);
        $this->entityManager->persist($probe);
        $this->entityManager->flush();

        foreach ($successes as $success) {
            $this->recordResult($probe, $success);
        }

        return $probe;
    }

    private function recordResult(Probe $probe, bool $success): void
    {
        $result = $success ? ProbeExecutionResult::success(10) : ProbeExecutionResult::failure('boom');
        $this->entityManager->persist(ProbeResult::record($probe, $result));
        $this->entityManager->flush();
    }
}
