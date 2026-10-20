<?php

namespace App\Controller;

use App\Entity\Ingredient;
use App\Form\IngredientType;
use App\Repository\IngredientRepository;
use App\Service\IngredientPhotoService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\RecipeSuggestionService;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class IngredientController extends AbstractController
{
    #[Route('/ingredients', name: 'app_ingredients_list', methods: ['GET'])]
    public function index(
        IngredientRepository $ingredientRepository,
        RecipeSuggestionService $suggestionService,
        Request $request
    ): Response {
        $session = $request->hasSession() ? $request->getSession() : null;
        $session?->set('app_mode', 'quoimanger');
        $hasSortInQuery = $request->query->has('sort');
        $hasOrderInQuery = $request->query->has('order');

        if ($hasSortInQuery) {
            $sort = (string) $request->query->get('sort');
            $session?->set('ingredients_sort', $sort);
        } else {
            $sort = (string) ($session?->get('ingredients_sort') ?? 'alpha');
        }

        $defaultOrder = match ($sort) {
            'recettes' => 'desc',
            default => 'asc', // 'alpha', 'rank'
        };

        if ($hasOrderInQuery) {
            $order = strtolower((string) $request->query->get('order', ''));
            if (!in_array($order, ['asc', 'desc'], true)) {
                $order = $defaultOrder;
            }
            $session?->set('ingredients_order', $order);
        } elseif ($hasSortInQuery) {
            $order = $defaultOrder;
            $session?->set('ingredients_order', $order);
        } else {
            $order = (string) ($session?->get('ingredients_order') ?? $defaultOrder);
            if (!in_array($order, ['asc', 'desc'], true)) {
                $order = $defaultOrder;
            }
        }

        $ingredients = $ingredientRepository->findAll();
        $ranks = $suggestionService->calculateIngredientRanks($ingredients);

        usort($ingredients, function (Ingredient $a, Ingredient $b) use ($sort, $order, $ranks) {
            $isAsc = ($order === 'asc');

            if ($sort === 'rank') {
                $rankA = $ranks[$a->getId()] ?? null;
                $rankB = $ranks[$b->getId()] ?? null;
                if ($rankA === $rankB) {
                    $cmp = strcasecmp($a->getDesignation() ?? '', $b->getDesignation() ?? '');
                    return $isAsc ? $cmp : -$cmp;
                }
                if ($rankA === null) return 1;
                if ($rankB === null) return -1;
                return $isAsc ? ($rankA <=> $rankB) : ($rankB <=> $rankA);
            } elseif ($sort === 'recettes') {
                $countA = $a->getRecetteIngredients()->count();
                $countB = $b->getRecetteIngredients()->count();
                if ($countA === $countB) {
                    $cmp = strcasecmp($a->getDesignation() ?? '', $b->getDesignation() ?? '');
                    return $isAsc ? $cmp : -$cmp;
                }
                return $isAsc ? ($countA <=> $countB) : ($countB <=> $countA);
            } else { // 'alpha'
                $cmp = strcasecmp($a->getDesignation() ?? '', $b->getDesignation() ?? '');
                if ($cmp === 0) {
                    return ($a->getId() ?? 0) <=> ($b->getId() ?? 0);
                }
                return $isAsc ? $cmp : -$cmp;
            }
        });

        return $this->render('pages/pageComposant.html.twig', [
            'twig' => 'pages/ingredient/index',
            'ingredients' => $ingredients,
            'ranks' => $ranks,
            'currentSort' => $sort,
            'currentOrder' => $order,
        ]);
    }

    #[Route('/ingredient', name: 'app_ingredient_create', methods: ['GET', 'POST'])]
    #[Route('/ingredient/{id<\d+>}', name: 'app_ingredient_edit', methods: ['GET', 'POST'])]
    public function edit(
        ?Ingredient $ingredient,
        Request $request,
        EntityManagerInterface $em,
        IngredientPhotoService $photoService
    ): Response {
        $isNew = false;
        if (!$ingredient) {
            $ingredient = new Ingredient();
            $isNew = true;
        }

        $form = $this->createForm(IngredientType::class, $ingredient);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($request->request->get('delete_photo') === '1') {
                foreach ($ingredient->getPhotos() as $p) {
                    $photoService->delete($p);
                }
                $ingredient->setPhotos([]);
            } elseif ($request->request->has('delete_photos')) {
                $deletedPhotos = (array) $request->request->all('delete_photos');
                foreach ($deletedPhotos as $p) {
                    if (is_string($p) && $p !== '') {
                        $photoService->delete($p);
                        $ingredient->removePhoto($p);
                    }
                }
            }

            $photoFiles = $request->files->all('photos');
            if (empty($photoFiles)) {
                $rawPhoto = $request->files->get('photo');
                if ($rawPhoto instanceof UploadedFile) {
                    $photoFiles = [$rawPhoto];
                } elseif (is_array($rawPhoto)) {
                    $photoFiles = $rawPhoto;
                }
            }

            foreach ($photoFiles as $file) {
                if ($file instanceof UploadedFile && $file->isValid()) {
                    try {
                        $filename = $photoService->upload($file);
                        $ingredient->addPhoto($filename);
                    } catch (\Exception $e) {
                        $this->addFlash('warning', 'La photo n\'a pas pu être enregistrée : ' . $e->getMessage());
                    }
                }
            }

            $em->persist($ingredient);
            $em->flush();

            $this->addFlash('success', $isNew ? 'Ingrédient créé avec succès.' : 'Ingrédient mis à jour avec succès.');

            $sortParams = [];
            if ($savedSort = ($request->hasSession() ? $request->getSession()->get('ingredients_sort') : null)) {
                $sortParams['sort'] = $savedSort;
            }
            if ($savedOrder = ($request->hasSession() ? $request->getSession()->get('ingredients_order') : null)) {
                $sortParams['order'] = $savedOrder;
            }

            return $this->redirectToRoute('app_ingredients_list', $sortParams);
        }

        return $this->render('pages/pageComposant.html.twig', [
            'twig' => 'pages/ingredient/form',
            'ingredient' => $ingredient,
            'form' => $form->createView(),
            'isNew' => $isNew,
        ]);
    }

    #[Route('/ingredient/{id<\d+>}/delete', name: 'app_ingredient_delete', methods: ['POST'])]
    public function delete(
        Ingredient $ingredient,
        Request $request,
        EntityManagerInterface $em,
        IngredientPhotoService $photoService
    ): Response {
        if ($this->isCsrfTokenValid('delete_ingredient_' . $ingredient->getId(), (string) $request->request->get('_token'))) {
            foreach ($ingredient->getPhotos() as $p) {
                $photoService->delete($p);
            }
            $em->remove($ingredient);
            $em->flush();
            $this->addFlash('success', 'Ingrédient supprimé.');
        }

        $sortParams = [];
        if ($savedSort = ($request->hasSession() ? $request->getSession()->get('ingredients_sort') : null)) {
            $sortParams['sort'] = $savedSort;
        }
        if ($savedOrder = ($request->hasSession() ? $request->getSession()->get('ingredients_order') : null)) {
            $sortParams['order'] = $savedOrder;
        }

        return $this->redirectToRoute('app_ingredients_list', $sortParams);
    }
}
