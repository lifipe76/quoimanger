<?php

namespace App\Controller;

use App\Entity\Recette;
use App\Entity\RecetteRealisation;
use App\Repository\RecetteRealisationRepository;
use App\Repository\RecetteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class HomeController extends AbstractController
{
    // #[IsGranted('ROLE_USER')]
    #[Route('/', name: 'home', methods: ['GET'])]
    public function home(
        RecetteRealisationRepository $realisationRepository,
        RecetteRepository $recetteRepository
    ): Response {
        $timeline = $realisationRepository->findTimeline();
        $recettes = $recetteRepository->findAllOrderedByLastRealisation();

        return $this->render('pages/home/index.html.twig', [
            'timeline' => $timeline,
            'recettes' => $recettes,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/realisation/add', name: 'app_realisation_add', methods: ['POST'])]
    public function addRealisation(
        Request $request,
        RecetteRepository $recetteRepository,
        EntityManagerInterface $em
    ): Response {
        $data = json_decode($request->getContent(), true) ?? [];
        $recetteId = (int) ($request->request->get('recette_id') ?? $data['recette_id'] ?? 0);
        $csrfToken = (string) ($request->request->get('_token') ?? $data['_token'] ?? '');

        if ($csrfToken !== '' && !$this->isCsrfTokenValid('add_realisation', $csrfToken)) {
            if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Accept', ''), 'application/json')) {
                return new JsonResponse(['error' => 'Jeton CSRF invalide.'], Response::HTTP_FORBIDDEN);
            }
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('home');
        }

        $recette = $recetteRepository->find($recetteId);
        if (!$recette) {
            if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Accept', ''), 'application/json')) {
                return new JsonResponse(['error' => 'Recette introuvable.'], Response::HTTP_NOT_FOUND);
            }
            $this->addFlash('danger', 'Recette introuvable.');
            return $this->redirectToRoute('home');
        }

        $realisation = new RecetteRealisation();
        $realisation->setRecette($recette);
        $realisation->setRealiseAt(new \DateTimeImmutable());

        $em->persist($realisation);
        $em->flush();

        if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Accept', ''), 'application/json')) {
            return new JsonResponse([
                'success' => true,
                'id' => $realisation->getId(),
                'recette' => $recette->getDesignation(),
                'realiseAt' => $realisation->getRealiseAt()?->format('d/m/Y à H:i'),
            ]);
        }

        $this->addFlash('success', sprintf('Repas enregistré avec succès : « %s » !', $recette->getDesignation()));

        return $this->redirectToRoute('home');
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/realisation/{id<\d+>}/delete', name: 'app_realisation_delete', methods: ['POST'])]
    public function deleteRealisation(
        RecetteRealisation $realisation,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if ($this->isCsrfTokenValid('delete_realisation_' . $realisation->getId(), (string) $request->request->get('_token'))) {
            $em->remove($realisation);
            $em->flush();
            $this->addFlash('success', 'Repas retiré de la timeline.');
        }

        return $this->redirectToRoute('home');
    }
}
