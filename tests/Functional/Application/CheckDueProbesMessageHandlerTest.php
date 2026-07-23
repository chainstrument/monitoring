<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application;

use App\Application\Probe\CheckDueProbesMessage;
use App\Domain\Probe\HttpProbeConfig;
use App\Domain\Probe\PingProbeConfig;
use App\Domain\Probe\ProbeResultStatus;
use App\Domain\Shared\Hostname;
use App\Domain\Shared\Url;
use App\Domain\Target\TargetType;
use App\Infrastructure\Doctrine\Entity\Probe;
use App\Infrastructure\Doctrine\Entity\ProbeResult;
use App\Infrastructure\Doctrine\Entity\Target;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

final class CheckDueProbesMessageHandlerTest extends KernelTestCase
{
    public function testOnlyDueProbesGetChecked(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        // Sonde TCP locale (comme les autres tests d'exécution) : pas de
        // dépendance réseau réelle pour vérifier que la sonde due est
        // effectivement exécutée.
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        self::assertNotFalse($server, \sprintf('Could not start local test TCP server: %s', $errstr));
        $address = stream_socket_get_name($server, false);
        self::assertIsString($address);
        $port = (int) substr($address, strrpos($address, ':') + 1);

        try {
            $target = Target::create('Cible pour le scheduler', TargetType::Website, 'https://example.com');
            $entityManager->persist($target);

            // Jamais vérifiée : due.
            $dueProbe = Probe::ping($target, new PingProbeConfig(new Hostname('127.0.0.1'), $port, 1000), intervalSeconds: 60);
            $entityManager->persist($dueProbe);

            // Vérifiée à l'instant, fréquence à 1h : pas due. Type HTTP pour
            // vérifier qu'aucun appel réseau n'est déclenché si elle est
            // ignorée (sinon ce test échouerait/serait lent).
            $notDueProbe = Probe::http($target, new HttpProbeConfig(new Url('https://example.com')), intervalSeconds: 3600);
            $entityManager->persist($notDueProbe);
            $entityManager->flush();

            $existingResult = ProbeResult::record($notDueProbe, \App\Domain\Probe\ProbeResult::success(10));
            $entityManager->persist($existingResult);
            $entityManager->flush();

            self::getContainer()->get(MessageBusInterface::class)->dispatch(new CheckDueProbesMessage());

            $resultRepository = $entityManager->getRepository(ProbeResult::class);

            self::assertCount(1, $resultRepository->findBy(['probe' => $dueProbe]));
            self::assertCount(1, $resultRepository->findBy(['probe' => $notDueProbe]));
            self::assertSame(
                ProbeResultStatus::Success,
                $resultRepository->findOneBy(['probe' => $dueProbe])?->status,
            );
        } finally {
            fclose($server);
        }
    }
}
