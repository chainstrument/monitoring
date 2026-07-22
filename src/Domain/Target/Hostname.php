<?php

declare(strict_types=1);

namespace App\Domain\Target;

final readonly class Hostname
{
    private const string HOSTNAME_PATTERN = '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i';

    public string $value;

    public function __construct(string $value)
    {
        if (!filter_var($value, \FILTER_VALIDATE_IP) && 1 !== preg_match(self::HOSTNAME_PATTERN, $value)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a valid hostname or IP address.', $value));
        }

        $this->value = $value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
