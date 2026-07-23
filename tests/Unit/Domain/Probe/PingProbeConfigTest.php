<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Probe;

use App\Domain\Probe\PingProbeConfig;
use App\Domain\Shared\Hostname;
use PHPUnit\Framework\TestCase;

final class PingProbeConfigTest extends TestCase
{
    public function testRoundTripsThroughArray(): void
    {
        $config = new PingProbeConfig(new Hostname('db.example.com'), 5432, 2000);

        $rebuilt = PingProbeConfig::fromArray($config->toArray());

        self::assertSame('db.example.com', $rebuilt->host->value);
        self::assertSame(5432, $rebuilt->port);
        self::assertSame(2000, $rebuilt->timeoutMs);
    }

    public function testRejectsAnOutOfRangePort(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PingProbeConfig(new Hostname('db.example.com'), 70_000);
    }

    public function testRejectsATimeoutThatIsTooLow(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PingProbeConfig(new Hostname('db.example.com'), 5432, 10);
    }
}
