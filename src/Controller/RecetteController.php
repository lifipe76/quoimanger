<?php

namespace App\Controller;

use App\Entity\Recette;
use App\Form\RecetteType;
use App\Repository\IngredientRepository;
use App\Repository\RecetteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class RecetteController extends AbstractController
{
    #[Route('/recettes', name: 'app_recettes_list', methods: ['GET'])]
    public function index(
        RecetteRepository $recetteRepository,
        \App\Service\RecipeSuggestionService $suggestionService,
        Request $request
    ): Response {
        $sort = $request->query->get('sort', 'recent'); // 'recent', 'oldest', 'note', 'alpha', 'id'
        if ($sort === 'date') {
            $sort = 'recent';
        }
        $allRecettes = $recetteRepository->findAll();
        $ranks = $suggestionService->calculateRecipeRanks($allRecettes);

        usort($allRecettes, function (Recette $a, Recette $b) use ($sort) {
            if ($sort === 'recent') {
                $dateA = $a->getLastRealiseAt();
                $dateB = $b->getLastRealiseAt();
                if ($dateA == $dateB) {
                    return ($b->getId() ?? 0) <=> ($a->getId() ?? 0);
                }
                if ($dateA === null) return 1;
                if ($dateB === null) return -1;
                return $dateB <=> $dateA;
            } elseif ($sort === 'oldest') {
                $dateA = $a->getLastRealiseAt();
                $dateB = $b->getLastRealiseAt();
                if ($dateA == $dateB) {
                    return ($a->getId() ?? 0) <=> ($b->getId() ?? 0);
                }
                if ($dateA === null) return 1;
                if ($dateB === null) return -1;
                return $dateA <=> $dateB;
            } elseif ($sort === 'note') {
                $noteA = $a->getAverageNote();
                $noteB = $b->getAverageNote();
                if ($noteA == $noteB) {
                    return $b->getNotesCount() <=> $a->getNotesCount();
                }
                if ($noteA === null) return 1;
                if ($noteB === null) return -1;
                return $noteB <=> $noteA;
            } elseif ($sort === 'alpha') {
                return strcmp($a->getDesignation() ?? '', $b->getDesignation() ?? '');
            } else { // 'id' ou par défaut
                return ($b->getId() ?? 0) <=> ($a->getId() ?? 0);
            }
        });

        return $this->render('pages/pageComposant.html.twig', [
            'twig' => 'pages/recette/index',
            'recettes' => $allRecettes,
            'ranks' => $ranks,
            'currentSort' => $sort,
        ]);
    }

    #[Route('/recette', name: 'app_recette_create', methods: ['GET', 'POST'])]
    #[Route('/recette/{id<\d+>}', name: 'app_recette_edit', methods: ['GET', 'POST'])]
    public function edit(?Recette $recette, Request $request, EntityManagerInterface $em, IngredientRepository $ingredientRepository): Response
    {
        $isNew = false;
        if (!$recette) {
            $recette = new Recette();
            $isNew = true;
        }

        $form = $this->createForm(RecetteType::class, $recette);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($recette->getRecetteIngredients() as $recetteIngredient) {
                if (!$recetteIngredient->getIngredient()) {
                    $recette->removeRecetteIngredient($recetteIngredient);
                } else {
                    $recetteIngredient->setRecette($recette);
                }
            }

            $em->persist($recette);
            $em->flush();

            $this->addFlash('success', $isNew ? 'Recette créée avec succès.' : 'Recette mise à jour avec succès.');

            return $this->redirectToRoute('app_recettes_list');
        }

        return $this->render('pages/pageComposant.html.twig', [
            'twig' => 'pages/recette/form',
            'recette' => $recette,
            'form' => $form->createView(),
            'isNew' => $isNew,
            'hasIngredients' => $ingredientRepository->count([]) > 0,
        ]);
    }

    #[Route('/recette/{id<\d+>}/delete', name: 'app_recette_delete', methods: ['POST'])]
    public function delete(Recette $recette, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_recette_' . $recette->getId(), (string) $request->request->get('_token'))) {
            $em->remove($recette);
            $em->flush();
            $this->addFlash('success', 'Recette supprimée.');
        }

        return $this->redirectToRoute('app_recettes_list');
    }
}
