<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Incident;

use App\Domain\Incident\IncidentDetector;
use App\Domain\Incident\ProbeState;
use App\Domain\Probe\ProbeResultStatus;
use PHPUnit\Framework\TestCase;

final class IncidentDetectorTest extends TestCase
{
    public function testASingleFailureIsNotYetDown(): void
    {
        $detector = new IncidentDetector(consecutiveFailureThreshold: 3);

        $state = $detector->detect([ProbeResultStatus::Failure, ProbeResultStatus::Success]);

        self::assertSame(ProbeState::Up, $state);
    }

    public function testConsecutiveFailuresReachingTheThresholdAreDown(): void
    {
        $detector = new IncidentDetector(consecutiveFailureThreshold: 3);

        $state = $detector->detect([
            ProbeResultStatus::Failure,
            ProbeResultStatus::Failure,
            ProbeResultStatus::Failure,
            ProbeResultStatus::Success,
        ]);

        self::assertSame(ProbeState::Down, $state);
    }

    public function testASuccessInterruptingTheStreakPreventsDown(): void
    {
        $detector = new IncidentDetector(consecutiveFailureThreshold: 3);

        // Deux échecs, un succès, un échec : jamais 3 d'affilée.
        $state = $detector->detect([
            ProbeResultStatus::Failure,
            ProbeResultStatus::Success,
            ProbeResultStatus::Failure,
            ProbeResultStatus::Failure,
        ]);

        self::assertSame(ProbeState::Up, $state);
    }

    public function testARecentSuccessMeansBackToNormal(): void
    {
        $detector = new IncidentDetector(consecutiveFailureThreshold: 3);

        // Même après une longue série d'échecs, un succès le plus récent = UP.
        $state = $detector->detect([
            ProbeResultStatus::Success,
            ProbeResultStatus::Failure,
            ProbeResultStatus::Failure,
            ProbeResultStatus::Failure,
        ]);

        self::assertSame(ProbeState::Up, $state);
    }

    public function testNoHistoryIsUp(): void
    {
        $detector = new IncidentDetector();

        self::assertSame(ProbeState::Up, $detector->detect([]));
    }
}
