<?php

declare(strict_types=1);

namespace App\Domain\Incident;

use App\Domain\Probe\ProbeResultStatus;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Logique pure de détection d'incident (anti-flapping) : une sonde ne
 * bascule en DOWN qu'après $consecutiveFailureThreshold échecs D'AFFILÉE
 * parmi les plus récents — un échec isolé au milieu d'une série de succès
 * ne déclenche rien.
 */
final readonly class IncidentDetector
{
    public function __construct(
        #[Autowire('%env(int:INCIDENT_FAILURE_THRESHOLD)%')]
        public int $consecutiveFailureThreshold = 3,
    ) {
    }

    /**
     * @param list<ProbeResultStatus> $recentStatusesNewestFirst du plus récent au plus ancien
     */
    public function detect(array $recentStatusesNewestFirst): ProbeState
    {
        $consecutiveFailures = 0;

        foreach ($recentStatusesNewestFirst as $status) {
            if (ProbeResultStatus::Failure !== $status) {
                break;
            }

            ++$consecutiveFailures;
        }

        return $consecutiveFailures >= $this->consecutiveFailureThreshold ? ProbeState::Down : ProbeState::Up;
    }
}
