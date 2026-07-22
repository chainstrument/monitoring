<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur de test uniquement : sert à vérifier la configuration
 * d'access_control (US-02.4) avant que /dashboard et /api n'aient de
 * vraies routes (Epic 07 et Epic 09). Chargé uniquement en environnement
 * de test, voir config/routes.yaml.
 */
final class ProbeController
{
    #[Route('/dashboard/_probe', name: 'test_probe_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        return new Response('ok');
    }

    #[Route('/api/_probe', name: 'test_probe_api', methods: ['GET'])]
    public function api(): Response
    {
        return new Response('ok');
    }
}
