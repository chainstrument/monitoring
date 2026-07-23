<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application;

use App\Application\Probe\ExecuteProbeMessage;
use App\Domain\Probe\HttpProbeConfig;
use App\Domain\Probe\ProbeResultStatus;
use App\Domain\Shared\Url;
use App\Domain\Target\TargetType;
use App\Infrastructure\Doctrine\Entity\Probe;
use App\Infrastructure\Doctrine\Entity\ProbeResult;
use App\Infrastructure\Doctrine\Entity\Target;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * US-05.3 : une erreur réseau typée (TransportExceptionInterface) doit
 * aboutir à un ProbeResult en échec persisté normalement — pas à une
 * exception qui remonte jusqu'au bus et déclenche un retry Messenger.
 * Seule une vraie erreur technique (ex. Probe introuvable) doit lever une
 * exception à ce niveau.
 */
final class ExecuteProbeMessageHandlerFailureTest extends KernelTestCase
{
    public function testATransportFailureIsRecordedAsAFailedProbeResultNotAnException(): void
    {
        self::bootKernel();

        self::getContainer()->set(HttpClientInterface::class, new MockHttpClient(
            static fn (): never => throw new TransportException('Could not resolve host.'),
        ));

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $target = Target::create('Site injoignable', TargetType::Website, 'https://unreachable.example.com');
        $probe = Probe::http($target, new HttpProbeConfig(new Url('https://unreachable.example.com')));
        $entityManager->persist($target);
        $entityManager->persist($probe);
        $entityManager->flush();
        \assert(null !== $probe->id);

        // Ne doit pas lever d'exception : le message est traité avec succès
        // du point de vue de Messenger, même si la sonde elle-même échoue.
        self::getContainer()->get(MessageBusInterface::class)
            ->dispatch(new ExecuteProbeMessage($probe->id));

        $result = $entityManager->getRepository(ProbeResult::class)->findOneBy(['probe' => $probe]);

        self::assertNotNull($result);
        self::assertSame(ProbeResultStatus::Failure, $result->status);
        self::assertSame('Could not resolve host.', $result->errorMessage);
    }

    public function testAMissingProbeRaisesARealExceptionForMessengerToRetry(): void
    {
        self::bootKernel();

        $this->expectException(\Symfony\Component\Messenger\Exception\HandlerFailedException::class);

        self::getContainer()->get(MessageBusInterface::class)
            ->dispatch(new ExecuteProbeMessage(999_999));
    }
}
