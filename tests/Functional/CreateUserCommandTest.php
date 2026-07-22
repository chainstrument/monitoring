<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Infrastructure\Doctrine\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CreateUserCommandTest extends KernelTestCase
{
    public function testItCreatesAUserInDatabase(): void
    {
        $kernel = self::bootKernel();

        $email = \sprintf('jane+%s@example.com', uniqid());

        $application = new Application($kernel);
        $command = $application->find('app:user:create');
        $tester = new CommandTester($command);
        $tester->setInputs([$email, 'Secret1234']);

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        self::assertNotNull($user);
        self::assertNotSame('Secret1234', $user->getPassword());
    }
}
