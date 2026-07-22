<?php

declare(strict_types=1);

namespace App\Domain\Target;

enum TargetType: string
{
    case Server = 'server';
    case Website = 'website';
    case Application = 'application';

    public function label(): string
    {
        return match ($this) {
            self::Server => 'Serveur',
            self::Website => 'Site web',
            self::Application => 'Application',
        };
    }
}
