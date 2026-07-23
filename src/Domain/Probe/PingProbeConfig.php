<?php

declare(strict_types=1);

namespace App\Domain\Probe;

use App\Domain\Shared\Hostname;

final readonly class PingProbeConfig
{
    private const int MIN_TIMEOUT_MS = 100;
    private const int MAX_TIMEOUT_MS = 30_000;

    public function __construct(
        public Hostname $host,
        public int $port,
        public int $timeoutMs = 5000,
    ) {
        if ($this->port < 1 || $this->port > 65_535) {
            throw new \InvalidArgumentException(\sprintf('"%d" is not a valid TCP port.', $this->port));
        }

        if ($this->timeoutMs < self::MIN_TIMEOUT_MS || $this->timeoutMs > self::MAX_TIMEOUT_MS) {
            throw new \InvalidArgumentException(\sprintf('Timeout must be between %d and %d ms.', self::MIN_TIMEOUT_MS, self::MAX_TIMEOUT_MS));
        }
    }

    /**
     * @param array{host: string, port: int, timeoutMs: int} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(new Hostname($data['host']), $data['port'], $data['timeoutMs']);
    }

    /**
     * @return array{host: string, port: int, timeoutMs: int}
     */
    public function toArray(): array
    {
        return [
            'host' => $this->host->value,
            'port' => $this->port,
            'timeoutMs' => $this->timeoutMs,
        ];
    }
}
