<?php

namespace App\Controller;

use App\Entity\Recette;
use App\Form\RecetteType;
use App\Repository\IngredientRepository;
use App\Repository\RecetteRepository;
use App\Service\RecettePhotoService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
        $sort = $request->query->get('sort', 'date'); // 'date', 'note', 'rank', 'alpha', 'id'
        if ($sort === 'recent') {
            $sort = 'date';
            $defaultOrder = 'desc';
        } elseif ($sort === 'oldest') {
            $sort = 'date';
            $defaultOrder = 'asc';
        } else {
            $defaultOrder = match ($sort) {
                'alpha', 'rank' => 'asc',
                default => 'desc', // 'date', 'note'
            };
        }

        $order = strtolower($request->query->get('order', ''));
        if (!in_array($order, ['asc', 'desc'], true)) {
            $order = $defaultOrder;
        }

        $allRecettes = $recetteRepository->findAll();
        $ranks = $suggestionService->calculateRecipeRanks($allRecettes);

        usort($allRecettes, function (Recette $a, Recette $b) use ($sort, $order, $ranks) {
            $isAsc = ($order === 'asc');

            if ($sort === 'rank') {
                $rankA = $ranks[$a->getId()] ?? null;
                $rankB = $ranks[$b->getId()] ?? null;
                if ($rankA === $rankB) {
                    return $isAsc
                        ? (($a->getId() ?? 0) <=> ($b->getId() ?? 0))
                        : (($b->getId() ?? 0) <=> ($a->getId() ?? 0));
                }
                if ($rankA === null) return 1;
                if ($rankB === null) return -1;
                return $isAsc ? ($rankA <=> $rankB) : ($rankB <=> $rankA);
            } elseif ($sort === 'date') {
                $dateA = $a->getLastRealiseAt();
                $dateB = $b->getLastRealiseAt();
                if ($dateA == $dateB) {
                    return $isAsc
                        ? (($a->getId() ?? 0) <=> ($b->getId() ?? 0))
                        : (($b->getId() ?? 0) <=> ($a->getId() ?? 0));
                }
                if ($dateA === null) return 1;
                if ($dateB === null) return -1;
                return $isAsc ? ($dateA <=> $dateB) : ($dateB <=> $dateA);
            } elseif ($sort === 'note') {
                $noteA = $a->getAverageNote();
                $noteB = $b->getAverageNote();
                if ($noteA == $noteB) {
                    return $isAsc
                        ? ($a->getNotesCount() <=> $b->getNotesCount())
                        : ($b->getNotesCount() <=> $a->getNotesCount());
                }
                if ($noteA === null) return 1;
                if ($noteB === null) return -1;
                return $isAsc ? ($noteA <=> $noteB) : ($noteB <=> $noteA);
            } elseif ($sort === 'alpha') {
                $cmp = strcasecmp($a->getDesignation() ?? '', $b->getDesignation() ?? '');
                return $isAsc ? $cmp : -$cmp;
            } else { // 'id' ou par défaut
                return $isAsc
                    ? (($a->getId() ?? 0) <=> ($b->getId() ?? 0))
                    : (($b->getId() ?? 0) <=> ($a->getId() ?? 0));
            }
        });

        return $this->render('pages/pageComposant.html.twig', [
            'twig' => 'pages/recette/index',
            'recettes' => $allRecettes,
            'ranks' => $ranks,
            'currentSort' => $sort,
            'currentOrder' => $order,
        ]);
    }

    #[Route('/recette', name: 'app_recette_create', methods: ['GET', 'POST'])]
    #[Route('/recette/{id<\d+>}', name: 'app_recette_edit', methods: ['GET', 'POST'])]
    public function edit(
        ?Recette $recette,
        Request $request,
        EntityManagerInterface $em,
        IngredientRepository $ingredientRepository,
        RecettePhotoService $recettePhotoService
    ): Response {
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

            if ($request->request->get('delete_photo') === '1') {
                if ($recette->getPhoto()) {
                    $recettePhotoService->delete($recette->getPhoto());
                    $recette->setPhoto(null);
                }
            }

            $photoFile = $request->files->get('photo');
            if ($photoFile instanceof UploadedFile && $photoFile->isValid()) {
                try {
                    if ($recette->getPhoto()) {
                        $recettePhotoService->delete($recette->getPhoto());
                    }
                    $filename = $recettePhotoService->upload($photoFile);
                    $recette->setPhoto($filename);
                } catch (\Exception $e) {
                    $this->addFlash('warning', 'La photo n\'a pas pu être enregistrée : ' . $e->getMessage());
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
    public function delete(
        Recette $recette,
        Request $request,
        EntityManagerInterface $em,
        RecettePhotoService $recettePhotoService
    ): Response {
        if ($this->isCsrfTokenValid('delete_recette_' . $recette->getId(), (string) $request->request->get('_token'))) {
            if ($recette->getPhoto()) {
                $recettePhotoService->delete($recette->getPhoto());
            }
            $em->remove($recette);
            $em->flush();
            $this->addFlash('success', 'Recette supprimée.');
        }

        return $this->redirectToRoute('app_recettes_list');
    }
}
