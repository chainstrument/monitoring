<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AccessControlTest extends WebTestCase
{
    public function testAnonymousAccessToDashboardRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/dashboard/_probe');

        self::assertResponseRedirects('/login');
    }

    public function testAnonymousAccessToApiReturns401(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/_probe');

        self::assertResponseStatusCodeSame(401);
    }
}
