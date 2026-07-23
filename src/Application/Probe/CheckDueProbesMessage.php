<?php

declare(strict_types=1);

namespace App\Application\Probe;

/**
 * Message "tick" déclenché périodiquement par le Scheduler (voir ProbeSchedule) :
 * il ne fait rien lui-même, son handler interroge les sondes dues et
 * dispatche un ExecuteProbeMessage par sonde due (une par une, pas de
 * message "batch" à charge du handler d'exécution).
 */
final readonly class CheckDueProbesMessage
{
}
