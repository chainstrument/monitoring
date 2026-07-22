<?php

declare(strict_types=1);

namespace App\Domain\Target;

final readonly class Url
{
    public string $value;

    public function __construct(string $value)
    {
        if (!filter_var($value, \FILTER_VALIDATE_URL) || !str_starts_with($value, 'http')) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a valid HTTP(S) URL.', $value));
        }

        $this->value = $value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
