<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Domain\User\Email;
use App\Infrastructure\Doctrine\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class LoginTest extends WebTestCase
{
    public function testSuccessfulLoginRedirectsToHomepage(): void
    {
        $client = static::createClient();
        $email = \sprintf('login-ok-%s@example.com', uniqid());
        $this->createUser($email, 'Secret1234');

        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            'email' => $email,
            'password' => 'Secret1234',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/');
    }

    public function testFailedLoginRedisplaysFormWithError(): void
    {
        $client = static::createClient();
        $email = \sprintf('login-ko-%s@example.com', uniqid());
        $this->createUser($email, 'Secret1234');

        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            'email' => $email,
            'password' => 'WrongPassword',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertSelectorTextContains('.error', 'Invalid credentials.');
    }

    private function createUser(string $email, string $plainPassword): void
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $user = User::register(new Email($email), hashedPassword: '');
        $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));

        $entityManager->persist($user);
        $entityManager->flush();
    }
}
