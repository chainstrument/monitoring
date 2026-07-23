<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Shared;

use App\Domain\Shared\Hostname;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HostnameTest extends TestCase
{
    #[DataProvider('validHostnames')]
    public function testAcceptsValidHostnamesAndIps(string $value): void
    {
        $hostname = new Hostname($value);

        self::assertSame($value, $hostname->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validHostnames(): iterable
    {
        yield 'simple domain' => ['example.com'];
        yield 'subdomain' => ['db.internal.example.com'];
        yield 'ipv4' => ['192.168.1.10'];
    }

    #[DataProvider('invalidHostnames')]
    public function testRejectsInvalidHostnames(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Hostname($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidHostnames(): iterable
    {
        yield 'empty string' => [''];
        yield 'has a scheme' => ['https://example.com'];
        yield 'has a space' => ['not a hostname'];
        yield 'underscore label' => ['_invalid_.com'];
    }
}
