<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Target;

use App\Domain\Target\TargetType;
use App\Infrastructure\Doctrine\Entity\Target;
use PHPUnit\Framework\TestCase;

final class TargetTest extends TestCase
{
    public function testServerRequiresAValidHostnameNotAUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Target::create('DB prod', TargetType::Server, 'https://example.com');
    }

    public function testWebsiteRequiresAValidUrlNotABareHostname(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Target::create('Site vitrine', TargetType::Website, 'example.com');
    }

    public function testCreateAcceptsAMatchingIdentifierForItsType(): void
    {
        $target = Target::create('DB prod', TargetType::Server, 'db.example.com', ['prod', 'db']);

        self::assertSame('DB prod', $target->name);
        self::assertSame(TargetType::Server, $target->type);
        self::assertSame('db.example.com', $target->identifier);
        self::assertSame(['prod', 'db'], $target->tags);
    }

    public function testRetagDeduplicatesTags(): void
    {
        $target = Target::create('Site vitrine', TargetType::Website, 'https://example.com');

        $target->retag(['prod', 'prod', 'web']);

        self::assertSame(['prod', 'web'], $target->tags);
    }
}
