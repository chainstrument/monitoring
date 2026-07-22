<?php

declare(strict_types=1);

namespace App\Domain\User;

final readonly class Email
{
    public string $value;

    public function __construct(string $value)
    {
        if (!filter_var($value, \FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a valid email address.', $value));
        }

        $this->value = $value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
