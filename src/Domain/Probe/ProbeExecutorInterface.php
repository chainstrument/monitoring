<?php

declare(strict_types=1);

namespace App\Domain\Probe;

use App\Infrastructure\Doctrine\Entity\Probe;

interface ProbeExecutorInterface
{
    public function supports(ProbeType $type): bool;

    public function execute(Probe $probe): ProbeResult;
}
