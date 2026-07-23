<?php

declare(strict_types=1);

namespace App\Controller;

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
        $tag = trim($request->query->getString('tag', ''));
        $typeParam = $request->query->getString('type', '');
        $type = '' === $typeParam ? null : TargetType::tryFrom($typeParam);

        return $this->render('dashboard/index.html.twig', [
            'targets' => $this->targetDashboardQuery->summaries('' === $tag ? null : $tag, $type),
            'tag' => $tag,
            'type' => $type,
            'types' => TargetType::cases(),
        ]);
    }
}
