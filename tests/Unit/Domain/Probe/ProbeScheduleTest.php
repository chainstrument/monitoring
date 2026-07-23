<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Probe;

use App\Domain\Probe\HttpProbeConfig;
use App\Domain\Shared\Url;
use App\Domain\Target\TargetType;
use App\Infrastructure\Doctrine\Entity\Probe;
use App\Infrastructure\Doctrine\Entity\Target;
use PHPUnit\Framework\TestCase;

final class ProbeScheduleTest extends TestCase
{
    public function testANeverCheckedProbeIsAlwaysDue(): void
    {
        $probe = $this->probe(intervalSeconds: 3600);

        self::assertTrue($probe->isDueAt(null, new \DateTimeImmutable()));
    }

    public function testAProbeCheckedLessThanItsIntervalAgoIsNotDue(): void
    {
        $probe = $this->probe(intervalSeconds: 60);
        $now = new \DateTimeImmutable('2026-01-01 12:00:30');
        $lastCheckedAt = new \DateTimeImmutable('2026-01-01 12:00:00');

        self::assertFalse($probe->isDueAt($lastCheckedAt, $now));
    }

    public function testAProbeCheckedAtLeastItsIntervalAgoIsDue(): void
    {
        $probe = $this->probe(intervalSeconds: 60);
        $now = new \DateTimeImmutable('2026-01-01 12:01:00');
        $lastCheckedAt = new \DateTimeImmutable('2026-01-01 12:00:00');

        self::assertTrue($probe->isDueAt($lastCheckedAt, $now));
    }

    private function probe(int $intervalSeconds): Probe
    {
        $target = Target::create('Site vitrine', TargetType::Website, 'https://example.com');

        return Probe::http($target, new HttpProbeConfig(new Url('https://example.com')), $intervalSeconds);
    }
}
