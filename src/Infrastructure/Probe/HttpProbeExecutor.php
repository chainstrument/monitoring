<?php

declare(strict_types=1);

namespace App\Infrastructure\Probe;

use App\Domain\Probe\ProbeExecutorInterface;
use App\Domain\Probe\ProbeResult;
use App\Domain\Probe\ProbeType;
use App\Infrastructure\Doctrine\Entity\Probe;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class HttpProbeExecutor implements ProbeExecutorInterface
{
    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    public function supports(ProbeType $type): bool
    {
        return ProbeType::Http === $type;
    }

    public function execute(Probe $probe): ProbeResult
    {
        $config = $probe->httpConfig();
        $startedAt = microtime(true);

        try {
            $response = $this->httpClient->request('GET', $config->url->value, [
                'timeout' => $config->timeoutMs / 1000,
            ]);
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            return ProbeResult::failure($e->getMessage());
        }

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($statusCode !== $config->expectedStatusCode) {
            return ProbeResult::failure(
                \sprintf('Expected HTTP status %d, got %d.', $config->expectedStatusCode, $statusCode),
                $latencyMs,
            );
        }

        return ProbeResult::success($latencyMs);
    }
}
