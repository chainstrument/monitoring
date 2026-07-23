<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\Target\TargetSummary;
use App\Domain\Target\TargetType;
use App\Infrastructure\Doctrine\Query\TargetDashboardQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    public function __construct(private readonly TargetDashboardQuery $targetDashboardQuery)
    {
    }

    #[Route('/dashboard', name: 'dashboard_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $tag = $this->tagFrom($request);
        $type = $this->typeFrom($request);

        return $this->render('dashboard/index.html.twig', [
            'targets' => $this->targetDashboardQuery->summaries($tag, $type),
            'tag' => $tag ?? '',
            'type' => $type,
            'types' => TargetType::cases(),
        ]);
    }

    /**
     * Fragment HTML rafraîchi périodiquement en AJAX (US-07.3) — pas de
     * Turbo/Mercure pour l'instant (différé en V2), un simple polling suffit.
     */
    #[Route('/dashboard/refresh', name: 'dashboard_refresh', methods: ['GET'])]
    public function refresh(Request $request): Response
    {
        /** @var list<TargetSummary> $targets */
        $targets = $this->targetDashboardQuery->summaries($this->tagFrom($request), $this->typeFrom($request));

        return $this->render('dashboard/_table.html.twig', ['targets' => $targets]);
    }

    private function tagFrom(Request $request): ?string
    {
        $tag = trim($request->query->getString('tag', ''));

        return '' === $tag ? null : $tag;
    }

    private function typeFrom(Request $request): ?TargetType
    {
        $typeParam = $request->query->getString('type', '');

        return '' === $typeParam ? null : TargetType::tryFrom($typeParam);
    }
}
