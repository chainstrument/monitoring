<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application;

use App\Application\Probe\ExecuteProbeMessage;
use App\Domain\Probe\PingProbeConfig;
use App\Domain\Shared\Hostname;
use App\Domain\Target\TargetType;
use App\Infrastructure\Doctrine\Entity\Incident;
use App\Infrastructure\Doctrine\Entity\Probe;
use App\Infrastructure\Doctrine\Entity\Target;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Vérifie le câblage réel (pas seulement IncidentStateUpdater isolé) :
 * ExecuteProbeMessageHandler doit bien déclencher la détection d'incident
 * après chaque exécution.
 */
final class ExecuteProbeMessageIncidentTest extends KernelTestCase
{
    public function testRepeatedFailuresThroughTheRealPipelineOpenAnIncident(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $messageBus = self::getContainer()->get(MessageBusInterface::class);

        // Port fermé (serveur ouvert puis aussitôt refermé) : chaque exécution échoue réellement.
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        self::assertNotFalse($server);
        $address = stream_socket_get_name($server, false);
        self::assertIsString($address);
        $port = (int) substr($address, strrpos($address, ':') + 1);
        fclose($server);

        $target = Target::create('Cible pipeline incident', TargetType::Server, '127.0.0.1');
        $probe = Probe::ping($target, new PingProbeConfig(new Hostname('127.0.0.1'), $port, 500));
        $entityManager->persist($target);
        $entityManager->persist($probe);
        $entityManager->flush();
        \assert(null !== $probe->id);

        $messageBus->dispatch(new ExecuteProbeMessage($probe->id));
        self::assertNull($entityManager->getRepository(Incident::class)->findOneBy(['probe' => $probe]));

        $messageBus->dispatch(new ExecuteProbeMessage($probe->id));
        $messageBus->dispatch(new ExecuteProbeMessage($probe->id));

        $incident = $entityManager->getRepository(Incident::class)->findOneBy(['probe' => $probe]);
        self::assertNotNull($incident);
        self::assertTrue($incident->isOpen());
    }
}
