<?php

declare(strict_types=1);

namespace App\Form;

use App\Domain\Target\TargetType;
use App\Infrastructure\Doctrine\Entity\Target;
use Symfony\Component\Validator\Constraints as Assert;

final class TargetFormData
{
    #[Assert\NotBlank(message: 'Le nom ne peut pas être vide.')]
    #[Assert\Length(max: 255)]
    public ?string $name = null;

    #[Assert\NotNull(message: 'Le type est obligatoire.')]
    public ?TargetType $type = null;

    #[Assert\NotBlank(message: "L'adresse ne peut pas être vide.")]
    #[Assert\Length(max: 255)]
    public ?string $identifier = null;

    public static function fromTarget(Target $target): self
    {
        $data = new self();
        $data->name = $target->name;
        $data->type = $target->type;
        $data->identifier = $target->identifier;

        return $data;
    }
}
