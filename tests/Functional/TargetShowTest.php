<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Domain\Probe\HttpProbeConfig;
use App\Domain\Probe\ProbeResult as ProbeExecutionResult;
use App\Domain\Shared\Url;
use App\Domain\Target\TargetType;
use App\Domain\User\Email;
use App\Infrastructure\Doctrine\Entity\Probe;
use App\Infrastructure\Doctrine\Entity\ProbeResult;
use App\Infrastructure\Doctrine\Entity\Target;
use App\Infrastructure\Doctrine\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class TargetShowTest extends WebTestCase
{
    public function testItShowsHistoryAndComputesUptime(): void
    {
        $client = static::createClient();
        $this->login($client);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $target = Target::create('Cible détail', TargetType::Website, 'https://detail.example.com');
        $probe = Probe::http($target, new HttpProbeConfig(new Url('https://detail.example.com')));
        $entityManager->persist($target);
        $entityManager->persist($probe);
        $entityManager->flush();

        // 3 succès, 1 échec => 75% d'uptime.
        $entityManager->persist(ProbeResult::record($probe, ProbeExecutionResult::success(10)));
        $entityManager->persist(ProbeResult::record($probe, ProbeExecutionResult::success(12)));
        $entityManager->persist(ProbeResult::record($probe, ProbeExecutionResult::success(11)));
        $entityManager->persist(ProbeResult::record($probe, ProbeExecutionResult::failure('boom')));
        $entityManager->flush();
        \assert(null !== $target->id);

        $client->request('GET', \sprintf('/targets/%d', $target->id));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', '75%');
        self::assertSelectorTextContains('body', 'Cible détail');
        self::assertSelectorExists('canvas');
    }

    public function testATargetWithoutAnyResultShowsNoData(): void
    {
        $client = static::createClient();
        $this->login($client);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $target = Target::create('Cible détail vide', TargetType::Website, 'https://empty.example.com');
        $entityManager->persist($target);
        $entityManager->flush();
        \assert(null !== $target->id);

        $client->request('GET', \sprintf('/targets/%d', $target->id));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Aucune sonde configurée');
    }

    private function login(KernelBrowser $client): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $email = \sprintf('show-%s@example.com', uniqid());
        $user = User::register(new Email($email), hashedPassword: '');
        $user->setPassword($passwordHasher->hashPassword($user, 'Secret1234'));
        $entityManager->persist($user);
        $entityManager->flush();

        $client->loginUser($user);
    }
}
