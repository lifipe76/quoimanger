<?php

namespace App\Controller;

use App\Entity\Frigo;
use App\Entity\User;
use App\Repository\FrigoRepository;
use App\Repository\RecetteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class FrigoController extends AbstractController
{
    #[Route('/frigo/add', name: 'app_frigo_add', methods: ['POST'])]
    public function add(
        Request $request,
        RecetteRepository $recetteRepository,
        FrigoRepository $frigoRepository,
        EntityManagerInterface $em
    ): Response {
        /** @var User|null $user */
        $user = $this->getUser();
        $famille = $user?->getFamille();

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('frigo_add', $token)) {
            if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Accept', ''), 'application/json')) {
                return new JsonResponse(['error' => 'Jeton CSRF invalide.'], Response::HTTP_FORBIDDEN);
            }
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('home');
        }

        $recetteId = (int) $request->request->get('recette_id');
        $recette = $recetteRepository->find($recetteId);
        if (!$recette) {
            if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Accept', ''), 'application/json')) {
                return new JsonResponse(['error' => 'Recette introuvable.'], Response::HTTP_NOT_FOUND);
            }
            $this->addFlash('danger', 'Recette introuvable.');
            return $this->redirectToRoute('home');
        }

        $existing = $frigoRepository->findOneByRecetteAndUserOrFamille($recette, $user, $famille);
        if (!$existing) {
            $frigo = new Frigo();
            $frigo->setRecette($recette);
            $frigo->setUser($user);
            $frigo->setFamille($famille);

            $em->persist($frigo);
            $em->flush();

            $message = sprintf('« %s » a été ajouté à votre frigo.', $recette->getDesignation());
            $this->addFlash('success', $message);
        } else {
            $message = sprintf('« %s » est déjà dans votre frigo.', $recette->getDesignation());
            $this->addFlash('info', $message);
        }

        if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Accept', ''), 'application/json')) {
            return new JsonResponse([
                'success' => true,
                'message' => $message,
                'recetteId' => $recette->getId(),
                'recetteName' => $recette->getDesignation(),
            ]);
        }

        return $this->redirectToRoute('home');
    }

    #[Route('/frigo/remove', name: 'app_frigo_remove', methods: ['POST'])]
    public function remove(
        Request $request,
        RecetteRepository $recetteRepository,
        FrigoRepository $frigoRepository,
        EntityManagerInterface $em
    ): Response {
        /** @var User|null $user */
        $user = $this->getUser();
        $famille = $user?->getFamille();

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('frigo_remove', $token)) {
            if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Accept', ''), 'application/json')) {
                return new JsonResponse(['error' => 'Jeton CSRF invalide.'], Response::HTTP_FORBIDDEN);
            }
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('home');
        }

        $recetteId = (int) $request->request->get('recette_id');
        $recette = $recetteRepository->find($recetteId);
        if (!$recette) {
            if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Accept', ''), 'application/json')) {
                return new JsonResponse(['error' => 'Recette introuvable.'], Response::HTTP_NOT_FOUND);
            }
            $this->addFlash('danger', 'Recette introuvable.');
            return $this->redirectToRoute('home');
        }

        $frigoRepository->removeByRecette($recette, $user, $famille);

        $message = sprintf('« %s » a été retiré de votre frigo.', $recette->getDesignation());
        $this->addFlash('success', $message);

        if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Accept', ''), 'application/json')) {
            return new JsonResponse([
                'success' => true,
                'message' => $message,
                'recetteId' => $recette->getId(),
            ]);
        }

        return $this->redirectToRoute('home');
    }
}
