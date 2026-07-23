<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Domain\Target\Hostname;
use App\Domain\Target\TargetType;
use App\Domain\Target\Url;
use Doctrine\ORM\Mapping as ORM;

/**
 * Décision de modélisation (US-03.1) : une seule table `target` couvrant les
 * trois types de cible (serveur/site web/application), avec un champ
 * `identifier` générique dont le format est validé selon `type` via les
 * Value Objects Url/Hostname. On évite le Single Table Inheritance Doctrine :
 * les trois types partagent aujourd'hui exactement les mêmes champs, une
 * hiérarchie de classes ajouterait de la complexité sans bénéfice réel tant
 * qu'aucun comportement ne diverge entre eux.
 */
#[ORM\Entity]
#[ORM\Table(name: 'target')]
class Target
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public private(set) ?int $id = null;

    #[ORM\Column(length: 255)]
    public private(set) string $name;

    #[ORM\Column(enumType: TargetType::class)]
    public private(set) TargetType $type;

    #[ORM\Column(length: 255)]
    public private(set) string $identifier;

    /**
     * Stocké en texte simple (liste séparée par des virgules), pas en JSON
     * natif : PostgreSQL n'autorise pas LIKE sur une colonne `json`, or le
     * filtre par tag (US-03.3) en a besoin. Une colonne texte reste largement
     * suffisante tant qu'on n'a pas de vraie relation Many-to-Many Tag.
     *
     * @var list<string>
     */
    #[ORM\Column(type: 'simple_array')]
    public private(set) array $tags = [];

    #[ORM\Column]
    public private(set) \DateTimeImmutable $createdAt;

    private function __construct()
    {
    }

    /**
     * @param list<string> $tags
     */
    public static function create(string $name, TargetType $type, string $identifier, array $tags = []): self
    {
        $target = new self();
        $target->name = $name;
        $target->type = $type;
        $target->identifier = self::validateIdentifier($type, $identifier);
        $target->tags = array_values(array_unique($tags));
        $target->createdAt = new \DateTimeImmutable();

        return $target;
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    public function changeIdentifier(TargetType $type, string $identifier): void
    {
        $this->type = $type;
        $this->identifier = self::validateIdentifier($type, $identifier);
    }

    /**
     * @param list<string> $tags
     */
    public function retag(array $tags): void
    {
        $this->tags = array_values(array_unique($tags));
    }

    private static function validateIdentifier(TargetType $type, string $identifier): string
    {
        return match ($type) {
            TargetType::Server => (string) new Hostname($identifier),
            TargetType::Website, TargetType::Application => (string) new Url($identifier),
        };
    }
}
