<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Shared;

use App\Domain\Shared\Url;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UrlTest extends TestCase
{
    public function testAcceptsAValidHttpUrl(): void
    {
        $url = new Url('https://example.com/status');

        self::assertSame('https://example.com/status', $url->value);
    }

    #[DataProvider('invalidUrls')]
    public function testRejectsInvalidUrls(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Url($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidUrls(): iterable
    {
        yield 'empty string' => [''];
        yield 'not a url' => ['not a url'];
        yield 'ftp scheme' => ['ftp://example.com'];
        yield 'bare hostname' => ['example.com'];
    }
}
