<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application;

use App\Application\Probe\ExecuteProbeMessage;
use App\Domain\Probe\PingProbeConfig;
use App\Domain\Probe\ProbeResultStatus;
use App\Domain\Shared\Hostname;
use App\Domain\Target\TargetType;
use App\Infrastructure\Doctrine\Entity\Probe;
use App\Infrastructure\Doctrine\Entity\ProbeResult;
use App\Infrastructure\Doctrine\Entity\Target;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

final class ExecuteProbeMessageHandlerTest extends KernelTestCase
{
    public function testDispatchingTheMessageExecutesTheProbeAndPersistsAResult(): void
    {
        self::bootKernel();

        // Sonde TCP locale plutôt qu'un vrai appel HTTP : le test reste
        // rapide et fiable, sans dépendance réseau externe (même principe
        // que les tests unitaires de PingProbeExecutor, Epic 04).
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        self::assertNotFalse($server, \sprintf('Could not start local test TCP server: %s', $errstr));
        $address = stream_socket_get_name($server, false);
        self::assertIsString($address);
        $port = (int) substr($address, strrpos($address, ':') + 1);

        try {
            $entityManager = self::getContainer()->get(EntityManagerInterface::class);

            $target = Target::create('Serveur DB', TargetType::Server, '127.0.0.1');
            $probe = Probe::ping($target, new PingProbeConfig(new Hostname('127.0.0.1'), $port, 1000));
            $entityManager->persist($target);
            $entityManager->persist($probe);
            $entityManager->flush();
            \assert(null !== $probe->id);

            self::getContainer()->get(MessageBusInterface::class)
                ->dispatch(new ExecuteProbeMessage($probe->id));

            $result = $entityManager->getRepository(ProbeResult::class)->findOneBy(['probe' => $probe]);

            self::assertNotNull($result);
            self::assertSame(ProbeResultStatus::Success, $result->status);
            self::assertNotNull($result->latencyMs);
        } finally {
            fclose($server);
        }
    }
}
