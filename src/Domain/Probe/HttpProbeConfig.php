<?php

declare(strict_types=1);

namespace App\Domain\Probe;

use App\Domain\Shared\Url;

final readonly class HttpProbeConfig
{
    private const int MIN_TIMEOUT_MS = 100;
    private const int MAX_TIMEOUT_MS = 30_000;

    public function __construct(
        public Url $url,
        public int $expectedStatusCode = 200,
        public int $timeoutMs = 5000,
    ) {
        if ($this->expectedStatusCode < 100 || $this->expectedStatusCode > 599) {
            throw new \InvalidArgumentException(\sprintf('"%d" is not a valid HTTP status code.', $this->expectedStatusCode));
        }

        if ($this->timeoutMs < self::MIN_TIMEOUT_MS || $this->timeoutMs > self::MAX_TIMEOUT_MS) {
            throw new \InvalidArgumentException(\sprintf('Timeout must be between %d and %d ms.', self::MIN_TIMEOUT_MS, self::MAX_TIMEOUT_MS));
        }
    }

    /**
     * @param array{url: string, expectedStatusCode: int, timeoutMs: int} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(new Url($data['url']), $data['expectedStatusCode'], $data['timeoutMs']);
    }

    /**
     * @return array{url: string, expectedStatusCode: int, timeoutMs: int}
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url->value,
            'expectedStatusCode' => $this->expectedStatusCode,
            'timeoutMs' => $this->timeoutMs,
        ];
    }
}
