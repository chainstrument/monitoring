<?php

declare(strict_types=1);

namespace App\Application\Probe;

use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Déclenche un "tick" (CheckDueProbesMessage) toutes les 10 secondes : c'est
 * le tick, pas le Schedule, qui interroge les sondes réellement dues (leur
 * fréquence propre est définie par Probe::$intervalSeconds, pas ici).
 */
#[AsSchedule]
final class ProbeSchedule implements ScheduleProviderInterface
{
    public function __construct(private readonly CacheInterface $cache)
    {
    }

    public function getSchedule(): Schedule
    {
        return new Schedule()
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)
            ->add(RecurringMessage::every('10 seconds', new CheckDueProbesMessage()))
        ;
    }
}
