<?php

declare(strict_types=1);

namespace App\Infrastructure\Probe;

use App\Domain\Probe\ProbeExecutorInterface;
use App\Domain\Probe\ProbeResult;
use App\Domain\Probe\ProbeType;
use App\Infrastructure\Doctrine\Entity\Probe;

/**
 * Exécute une sonde "Ping" via une connexion TCP plutôt qu'un vrai ping
 * ICMP : ICMP nécessite des privilèges root (CAP_NET_RAW), souvent
 * indisponibles dans un conteneur Docker, alors qu'une connexion TCP sur le
 * port fourni est fiable et ne demande aucun privilège particulier.
 */
final class PingProbeExecutor implements ProbeExecutorInterface
{
    public function supports(ProbeType $type): bool
    {
        return ProbeType::Ping === $type;
    }

    public function execute(Probe $probe): ProbeResult
    {
        $config = $probe->pingConfig();
        $startedAt = microtime(true);

        $errno = 0;
        $errstr = '';
        $connection = @fsockopen($config->host->value, $config->port, $errno, $errstr, $config->timeoutMs / 1000);

        if (false === $connection) {
            return ProbeResult::failure(\sprintf('%s (errno %d)', $errstr, $errno));
        }

        fclose($connection);

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        return ProbeResult::success($latencyMs);
    }
}
