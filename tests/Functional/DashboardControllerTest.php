<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Domain\Probe\HttpProbeConfig;
use App\Domain\Probe\ProbeResult as ProbeExecutionResult;
use App\Domain\Shared\Url;
use App\Domain\Target\TargetType;
use App\Domain\User\Email;
use App\Infrastructure\Doctrine\Entity\Incident;
use App\Infrastructure\Doctrine\Entity\Probe;
use App\Infrastructure\Doctrine\Entity\ProbeResult;
use App\Infrastructure\Doctrine\Entity\Target;
use App\Infrastructure\Doctrine\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class DashboardControllerTest extends WebTestCase
{
    public function testItShowsTheCurrentStatusOfEachTarget(): void
    {
        $client = static::createClient();
        $this->login($client);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        // UP : dernier résultat en succès, pas d'incident ouvert.
        $upTarget = Target::create('Cible dashboard UP', TargetType::Website, 'https://up.example.com');
        $upProbe = $this->makeHttpProbe($upTarget);
        $entityManager->persist($upTarget);
        $entityManager->persist($upProbe);
        $entityManager->flush();
        $entityManager->persist(ProbeResult::record($upProbe, ProbeExecutionResult::success(20)));
        $entityManager->flush();

        // DOWN : un incident ouvert existe pour la sonde.
        $downTarget = Target::create('Cible dashboard DOWN', TargetType::Website, 'https://down.example.com');
        $downProbe = $this->makeHttpProbe($downTarget);
        $entityManager->persist($downTarget);
        $entityManager->persist($downProbe);
        $entityManager->flush();
        $entityManager->persist(ProbeResult::record($downProbe, ProbeExecutionResult::failure('boom')));
        $entityManager->persist(Incident::open($downProbe));
        $entityManager->flush();

        // UNKNOWN : aucune sonde exécutée pour l'instant.
        $unknownTarget = Target::create('Cible dashboard UNKNOWN', TargetType::Website, 'https://unknown.example.com');
        $entityManager->persist($unknownTarget);
        $entityManager->flush();

        $crawler = $client->request('GET', '/dashboard');

        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('tbody tr');
        self::assertGreaterThanOrEqual(3, $rows->count());
        self::assertSelectorTextContains('body', 'Cible dashboard UP');
        self::assertSelectorTextContains('body', 'Cible dashboard DOWN');
        self::assertSelectorTextContains('body', 'Cible dashboard UNKNOWN');
        self::assertStringContainsString('status-up">UP', (string) $crawler->filter('tbody')->html());
        self::assertStringContainsString('status-down">DOWN', (string) $crawler->filter('tbody')->html());
        self::assertStringContainsString('status-unknown">UNKNOWN', (string) $crawler->filter('tbody')->html());
    }

    public function testFilteringByTypeExcludesOtherTypes(): void
    {
        $client = static::createClient();
        $this->login($client);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $website = Target::create('Cible filtre website', TargetType::Website, 'https://filter.example.com');
        $server = Target::create('Cible filtre server', TargetType::Server, 'filter.example.com');
        $entityManager->persist($website);
        $entityManager->persist($server);
        $entityManager->flush();

        $client->request('GET', '/dashboard', ['type' => 'server']);

        self::assertSelectorTextContains('table', 'Cible filtre server');
        self::assertSelectorTextNotContains('table', 'Cible filtre website');
    }

    public function testTheRefreshEndpointReturnsAnUpdatedFragmentReflectingNewData(): void
    {
        $client = static::createClient();
        $this->login($client);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $target = Target::create('Cible refresh', TargetType::Website, 'https://refresh.example.com');
        $entityManager->persist($target);
        $entityManager->flush();

        $client->request('GET', '/dashboard/refresh');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Cible refresh');
        // Un fragment, pas une page complète.
        self::assertSelectorNotExists('html head title');

        $probe = $this->makeHttpProbe($target);
        $entityManager->persist($probe);
        $entityManager->flush();
        $entityManager->persist(ProbeResult::record($probe, ProbeExecutionResult::success(42)));
        $entityManager->flush();

        $client->request('GET', '/dashboard/refresh');

        self::assertSelectorTextContains('body', '42 ms');
    }

    private function makeHttpProbe(Target $target): Probe
    {
        return Probe::http($target, new HttpProbeConfig(new Url($target->identifier)));
    }

    private function login(KernelBrowser $client): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $email = \sprintf('dashboard-%s@example.com', uniqid());
        $user = User::register(new Email($email), hashedPassword: '');
        $user->setPassword($passwordHasher->hashPassword($user, 'Secret1234'));
        $entityManager->persist($user);
        $entityManager->flush();

        $client->loginUser($user);
    }
}
