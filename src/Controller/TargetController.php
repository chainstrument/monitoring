<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\TargetFormData;
use App\Form\TargetFormType;
use App\Infrastructure\Doctrine\Entity\Target;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/targets')]
final class TargetController extends AbstractController
{
    private const int PER_PAGE = 20;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route('', name: 'target_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));

        $queryBuilder = $this->entityManager->getRepository(Target::class)
            ->createQueryBuilder('t')
            ->orderBy('t.createdAt', 'DESC');

        $totalItems = count(new Paginator($queryBuilder->getQuery()));

        $queryBuilder
            ->setFirstResult(self::PER_PAGE * ($page - 1))
            ->setMaxResults(self::PER_PAGE);

        return $this->render('target/index.html.twig', [
            'targets' => new Paginator($queryBuilder->getQuery()),
            'page' => $page,
            'totalPages' => (int) max(1, ceil($totalItems / self::PER_PAGE)),
        ]);
    }

    #[Route('/new', name: 'target_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $data = new TargetFormData();
        $form = $this->createForm(TargetFormType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            \assert(null !== $data->name && null !== $data->type && null !== $data->identifier);

            try {
                $target = Target::create($data->name, $data->type, $data->identifier);
            } catch (\InvalidArgumentException $e) {
                $form->get('identifier')->addError(new FormError($e->getMessage()));

                return $this->render('target/new.html.twig', ['form' => $form]);
            }

            $this->entityManager->persist($target);
            $this->entityManager->flush();
            $this->addFlash('success', \sprintf('Cible "%s" créée.', $target->name));

            return $this->redirectToRoute('target_index');
        }

        return $this->render('target/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'target_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Target $target): Response
    {
        $data = TargetFormData::fromTarget($target);
        $form = $this->createForm(TargetFormType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            \assert(null !== $data->name && null !== $data->type && null !== $data->identifier);

            try {
                $target->rename($data->name);
                $target->changeIdentifier($data->type, $data->identifier);
            } catch (\InvalidArgumentException $e) {
                $form->get('identifier')->addError(new FormError($e->getMessage()));

                return $this->render('target/edit.html.twig', ['form' => $form, 'target' => $target]);
            }

            $this->entityManager->flush();
            $this->addFlash('success', \sprintf('Cible "%s" modifiée.', $target->name));

            return $this->redirectToRoute('target_index');
        }

        return $this->render('target/edit.html.twig', ['form' => $form, 'target' => $target]);
    }

    #[Route('/{id}/delete', name: 'target_delete', methods: ['POST'])]
    public function delete(Request $request, Target $target): Response
    {
        if ($this->isCsrfTokenValid('delete-target-'.$target->id, $request->request->getString('_token'))) {
            $this->entityManager->remove($target);
            $this->entityManager->flush();
            $this->addFlash('success', \sprintf('Cible "%s" supprimée.', $target->name));
        }

        return $this->redirectToRoute('target_index');
    }
}
