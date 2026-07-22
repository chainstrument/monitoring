<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Domain\User\Email;
use App\Infrastructure\Doctrine\Entity\Target;
use App\Infrastructure\Doctrine\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class TargetControllerTest extends WebTestCase
{
    public function testCreatingAValidTargetPersistsItAndRedirects(): void
    {
        $client = static::createClient();
        $this->login($client);

        $crawler = $client->request('GET', '/targets/new');
        $form = $crawler->selectButton('Enregistrer')->form([
            'target_form[name]' => 'Site vitrine',
            'target_form[type]' => 'website',
            'target_form[identifier]' => 'https://example.com',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/targets');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Site vitrine');
    }

    public function testCreatingATargetWithMismatchedIdentifierRedisplaysFormWithError(): void
    {
        $client = static::createClient();
        $this->login($client);

        $crawler = $client->request('GET', '/targets/new');
        $form = $crawler->selectButton('Enregistrer')->form([
            'target_form[name]' => 'DB prod',
            'target_form[type]' => 'server',
            'target_form[identifier]' => 'https://not-a-hostname.example.com',
        ]);
        $client->submit($form);

        // Symfony renvoie 422 (et non 200) pour un formulaire réaffiché avec erreurs.
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'is not a valid hostname or IP address');
    }

    public function testEditingATargetUpdatesIt(): void
    {
        $client = static::createClient();
        $this->login($client);

        $client->request('GET', '/targets/new');
        $crawler = $client->getCrawler();
        $form = $crawler->selectButton('Enregistrer')->form([
            'target_form[name]' => 'Site à renommer',
            'target_form[type]' => 'website',
            'target_form[identifier]' => 'https://example.org',
        ]);
        $client->submit($form);

        $targetId = $this->findTargetIdByName($client, 'Site à renommer');

        $crawler = $client->request('GET', \sprintf('/targets/%d/edit', $targetId));
        $form = $crawler->selectButton('Enregistrer')->form([
            'target_form[name]' => 'Site renommé',
            'target_form[type]' => 'website',
            'target_form[identifier]' => 'https://example.org',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/targets');

        $renamed = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Target::class)->find($targetId);
        self::assertNotNull($renamed);
        self::assertSame('Site renommé', $renamed->name);
    }

    public function testDeletingATargetRemovesIt(): void
    {
        $client = static::createClient();
        $this->login($client);

        $client->request('GET', '/targets/new');
        $crawler = $client->getCrawler();
        $form = $crawler->selectButton('Enregistrer')->form([
            'target_form[name]' => 'À supprimer',
            'target_form[type]' => 'website',
            'target_form[identifier]' => 'https://example.net',
        ]);
        $client->submit($form);

        $targetId = $this->findTargetIdByName($client, 'À supprimer');

        $crawler = $client->request('GET', '/targets');
        $client->submit($crawler->filter(\sprintf('form[action="/targets/%d/delete"]', $targetId))->form());

        self::assertResponseRedirects('/targets');

        $stillThere = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Target::class)->find($targetId);
        self::assertNull($stillThere);
    }

    private function findTargetIdByName(KernelBrowser $client, string $name): int
    {
        $target = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Target::class)
            ->findOneBy(['name' => $name]);

        self::assertNotNull($target, \sprintf('Target "%s" should exist.', $name));
        \assert(null !== $target->id);

        return $target->id;
    }

    private function login(KernelBrowser $client): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $email = \sprintf('targets-%s@example.com', uniqid());
        $user = User::register(new Email($email), hashedPassword: '');
        $user->setPassword($passwordHasher->hashPassword($user, 'Secret1234'));
        $entityManager->persist($user);
        $entityManager->flush();

        $client->loginUser($user);
    }
}
