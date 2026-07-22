<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\User\Email;
use App\Infrastructure\Doctrine\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:create',
    description: 'Crée un utilisateur pouvant se connecter à l\'application',
)]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $emailInput = $io->ask('Email');
        if (!\is_string($emailInput) || '' === $emailInput) {
            $io->error('Email invalide.');

            return Command::FAILURE;
        }

        try {
            $email = new Email($emailInput);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $passwordInput = $io->askHidden('Mot de passe');
        if (!\is_string($passwordInput) || '' === $passwordInput) {
            $io->error('Le mot de passe ne peut pas être vide.');

            return Command::FAILURE;
        }

        // UserPasswordHasherInterface::hashPassword() a besoin d'une instance pour
        // résoudre l'algorithme (ici "auto" pour tout PasswordAuthenticatedUserInterface),
        // d'où la construction en deux temps : instance provisoire, puis mot de passe réel.
        $user = User::register($email, hashedPassword: '');
        $user->setPassword($this->passwordHasher->hashPassword($user, $passwordInput));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(\sprintf('Utilisateur "%s" créé.', $email->value));

        return Command::SUCCESS;
    }
}
