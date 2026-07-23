<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Probe;

use App\Domain\Probe\PingProbeConfig;
use App\Domain\Shared\Hostname;
use App\Domain\Target\TargetType;
use App\Infrastructure\Doctrine\Entity\Probe;
use App\Infrastructure\Doctrine\Entity\Target;
use App\Infrastructure\Probe\PingProbeExecutor;
use PHPUnit\Framework\TestCase;

final class PingProbeExecutorTest extends TestCase
{
    public function testSucceedsWhenTheTcpPortAcceptsConnections(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        self::assertNotFalse($server, \sprintf('Could not start local test TCP server: %s', $errstr));

        $address = stream_socket_get_name($server, false);
        self::assertIsString($address);
        $port = (int) substr($address, strrpos($address, ':') + 1);

        try {
            $probe = $this->probeFor(new PingProbeConfig(new Hostname('127.0.0.1'), $port, 1000));
            $result = new PingProbeExecutor()->execute($probe);

            self::assertTrue($result->isSuccess());
            self::assertNotNull($result->latencyMs);
        } finally {
            fclose($server);
        }
    }

    public function testFailsWhenNothingIsListeningOnThePort(): void
    {
        // On récupère un port libre puis on referme immédiatement le
        // serveur : rien n'écoute plus dessus, la connexion doit échouer.
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        self::assertNotFalse($server);
        $address = stream_socket_get_name($server, false);
        self::assertIsString($address);
        $port = (int) substr($address, strrpos($address, ':') + 1);
        fclose($server);

        $probe = $this->probeFor(new PingProbeConfig(new Hostname('127.0.0.1'), $port, 1000));
        $result = new PingProbeExecutor()->execute($probe);

        self::assertFalse($result->isSuccess());
        self::assertNotNull($result->errorMessage);
    }

    private function probeFor(PingProbeConfig $config): Probe
    {
        $target = Target::create('Serveur DB', TargetType::Server, '127.0.0.1');

        return Probe::ping($target, $config);
    }
}
