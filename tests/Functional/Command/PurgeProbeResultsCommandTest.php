<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Domain\Probe\HttpProbeConfig;
use App\Domain\Probe\ProbeResult as ProbeExecutionResult;
use App\Domain\Shared\Url;
use App\Domain\Target\TargetType;
use App\Infrastructure\Doctrine\Entity\Probe;
use App\Infrastructure\Doctrine\Entity\ProbeResult;
use App\Infrastructure\Doctrine\Entity\Target;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class PurgeProbeResultsCommandTest extends KernelTestCase
{
    public function testItDeletesOnlyResultsOlderThanTheRetentionPeriod(): void
    {
        $kernel = self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $target = Target::create('Cible pour la purge', TargetType::Website, 'https://example.com');
        $probe = Probe::http($target, new HttpProbeConfig(new Url('https://example.com')));
        $entityManager->persist($target);
        $entityManager->persist($probe);
        $entityManager->flush();

        $oldResult = ProbeResult::record($probe, ProbeExecutionResult::success(10));
        $recentResult = ProbeResult::record($probe, ProbeExecutionResult::success(10));
        $entityManager->persist($oldResult);
        $entityManager->persist($recentResult);
        $entityManager->flush();

        // checkedAt est fixé par le VO à l'instant présent ; on le recule
        // directement en base pour simuler un résultat vieux de 100 jours
        // (rétention par défaut : 90 jours, voir .env).
        $entityManager->getConnection()->executeStatement(
            'UPDATE probe_result SET checked_at = :threshold WHERE id = :id',
            ['threshold' => new \DateTimeImmutable('-100 days')->format('Y-m-d H:i:s'), 'id' => $oldResult->id],
        );
        $entityManager->clear();

        $application = new Application($kernel);
        $tester = new CommandTester($application->find('app:probe-result:purge'));
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();

        $resultRepository = $entityManager->getRepository(ProbeResult::class);
        self::assertNull($resultRepository->find($oldResult->id));
        self::assertNotNull($resultRepository->find($recentResult->id));
    }
}
