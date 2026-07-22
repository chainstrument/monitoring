<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Domain\User\Email;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'app_user')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public private(set) ?int $id = null;

    #[ORM\Column(length: 180)]
    public private(set) string $email;

    /** @var list<string> */
    #[ORM\Column]
    public private(set) array $roles = [];

    #[ORM\Column]
    public private(set) string $password;

    private function __construct()
    {
    }

    public static function register(Email $email, string $hashedPassword): self
    {
        $user = new self();
        $user->email = $email->value;
        $user->password = $hashedPassword;
        $user->roles = ['ROLE_USER'];

        return $user;
    }

    public function getEmail(): Email
    {
        return new Email($this->email);
    }

    /**
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @return list<string>
     *
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // Aucune donnée sensible temporaire à effacer pour l'instant.
    }
}
