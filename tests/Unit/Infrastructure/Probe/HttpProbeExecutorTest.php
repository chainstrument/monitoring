<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Probe;

use App\Domain\Probe\HttpProbeConfig;
use App\Domain\Shared\Url;
use App\Domain\Target\TargetType;
use App\Infrastructure\Doctrine\Entity\Probe;
use App\Infrastructure\Doctrine\Entity\Target;
use App\Infrastructure\Probe\HttpProbeExecutor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HttpProbeExecutorTest extends TestCase
{
    public function testSuccessfulCheckMatchingExpectedStatusCode(): void
    {
        $probe = $this->probeFor(new HttpProbeConfig(new Url('https://example.com'), 200));
        $executor = new HttpProbeExecutor(new MockHttpClient(new MockResponse('', ['http_code' => 200])));

        $result = $executor->execute($probe);

        self::assertTrue($result->isSuccess());
        self::assertNotNull($result->latencyMs);
    }

    public function testFailsWhenStatusCodeDoesNotMatch(): void
    {
        $probe = $this->probeFor(new HttpProbeConfig(new Url('https://example.com'), 200));
        $executor = new HttpProbeExecutor(new MockHttpClient(new MockResponse('', ['http_code' => 503])));

        $result = $executor->execute($probe);

        self::assertFalse($result->isSuccess());
        self::assertStringContainsString('Expected HTTP status 200, got 503', (string) $result->errorMessage);
    }

    public function testFailsOnTransportException(): void
    {
        $probe = $this->probeFor(new HttpProbeConfig(new Url('https://example.com'), 200));
        $mockClient = new MockHttpClient(static function (): never {
            throw new TransportException('Could not resolve host.');
        });
        $executor = new HttpProbeExecutor($mockClient);

        $result = $executor->execute($probe);

        self::assertFalse($result->isSuccess());
        self::assertSame('Could not resolve host.', $result->errorMessage);
    }

    private function probeFor(HttpProbeConfig $config): Probe
    {
        $target = Target::create('Site vitrine', TargetType::Website, 'https://example.com');

        return Probe::http($target, $config);
    }
}
