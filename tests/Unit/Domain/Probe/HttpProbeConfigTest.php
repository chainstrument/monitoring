<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Probe;

use App\Domain\Probe\HttpProbeConfig;
use App\Domain\Shared\Url;
use PHPUnit\Framework\TestCase;

final class HttpProbeConfigTest extends TestCase
{
    public function testRoundTripsThroughArray(): void
    {
        $config = new HttpProbeConfig(new Url('https://example.com'), 204, 3000);

        $rebuilt = HttpProbeConfig::fromArray($config->toArray());

        self::assertSame('https://example.com', $rebuilt->url->value);
        self::assertSame(204, $rebuilt->expectedStatusCode);
        self::assertSame(3000, $rebuilt->timeoutMs);
    }

    public function testRejectsAnOutOfRangeStatusCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new HttpProbeConfig(new Url('https://example.com'), 999);
    }

    public function testRejectsATimeoutThatIsTooLow(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new HttpProbeConfig(new Url('https://example.com'), 200, 10);
    }

    public function testRejectsATimeoutThatIsTooHigh(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new HttpProbeConfig(new Url('https://example.com'), 200, 60_000);
    }
}
