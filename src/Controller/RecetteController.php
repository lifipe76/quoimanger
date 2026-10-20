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
        \App\Repository\FrigoRepository $frigoRepository,
        \App\Service\RecipeSuggestionService $suggestionService,
        Request $request
    ): Response {
        /** @var \App\Entity\User|null $currentUser */
        $currentUser = $this->getUser();
        $famille = $currentUser?->getFamille();
        $frigoRecetteIds = $frigoRepository->findRecetteIdsForUserOrFamille($currentUser, $famille);

        $session = $request->hasSession() ? $request->getSession() : null;
        $session?->set('app_mode', 'quoimanger');
        $hasSortInQuery = $request->query->has('sort');
        $hasOrderInQuery = $request->query->has('order');

        if ($hasSortInQuery) {
            $sort = (string) $request->query->get('sort');
            $session?->set('recettes_sort', $sort);
        } else {
            $sort = (string) ($session?->get('recettes_sort') ?? 'date');
        }

        $isFrigoFilter = ($sort === 'frigo' || $request->query->getBoolean('frigo', false));
        if ($sort === 'recent') {
            $sort = 'date';
            $defaultOrder = 'desc';
        } elseif ($sort === 'oldest') {
            $sort = 'date';
            $defaultOrder = 'asc';
        } else {
            $defaultOrder = match ($sort) {
                'alpha', 'rank' => 'asc',
                default => 'desc', // 'date', 'note', 'frigo'
            };
        }

        if ($hasOrderInQuery) {
            $order = strtolower((string) $request->query->get('order', ''));
            if (!in_array($order, ['asc', 'desc'], true)) {
                $order = $defaultOrder;
            }
            $session?->set('recettes_order', $order);
        } elseif ($hasSortInQuery) {
            $order = $defaultOrder;
            $session?->set('recettes_order', $order);
        } else {
            $order = (string) ($session?->get('recettes_order') ?? $defaultOrder);
            if (!in_array($order, ['asc', 'desc'], true)) {
                $order = $defaultOrder;
            }
        }

        $allRecettes = $recetteRepository->findAll();
        if ($isFrigoFilter) {
            $allRecettes = array_values(array_filter(
                $allRecettes,
                fn(Recette $r) => in_array($r->getId(), $frigoRecetteIds, true)
            ));
        }
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
            'frigoRecetteIds' => $frigoRecetteIds,
            'isFrigo' => $isFrigoFilter,
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

            // Gestion de la suppression de photos
            if ($request->request->get('delete_photo') === '1') {
                foreach ($recette->getPhotos() as $p) {
                    $recettePhotoService->delete($p);
                }
                $recette->setPhotos([]);
            } elseif ($request->request->has('delete_photos')) {
                $deletedPhotos = (array) $request->request->all('delete_photos');
                foreach ($deletedPhotos as $p) {
                    if (is_string($p) && $p !== '') {
                        $recettePhotoService->delete($p);
                        $recette->removePhoto($p);
                    }
                }
            }

            // Gestion de l'upload de photos (multiples ou unique)
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
                        $filename = $recettePhotoService->upload($file);
                        $recette->addPhoto($filename);
                    } catch (\Exception $e) {
                        $this->addFlash('warning', 'La photo n\'a pas pu être enregistrée : ' . $e->getMessage());
                    }
                }
            }

            $destination = $request->query->get('destination', $request->request->get('destination', ''));

            $em->persist($recette);
            $em->flush();

            /** @var \App\Entity\User|null $currentUser */
            $currentUser = $this->getUser();
            $famille = $currentUser?->getFamille();

            if ($destination === 'frigo') {
                $frigoRepo = $em->getRepository(\App\Entity\Frigo::class);
                $existing = $frigoRepo->findOneByRecetteAndUserOrFamille($recette, $currentUser, $famille);
                if (!$existing) {
                    $frigo = new \App\Entity\Frigo();
                    $frigo->setRecette($recette);
                    $frigo->setUser($currentUser);
                    $frigo->setFamille($famille);
                    $em->persist($frigo);
                    $em->flush();
                }
                $this->addFlash('success', sprintf('Recette « %s » créée et ajoutée à votre frigo.', $recette->getDesignation()));
                return $this->redirectToRoute('home', ['open_frigo' => 1]);
            } elseif ($destination === 'repas') {
                $this->addFlash('success', sprintf('Recette « %s » créée avec succès.', $recette->getDesignation()));
                return $this->redirectToRoute('home', ['open_repas' => 1, 'selected_recette' => $recette->getId()]);
            } elseif ($destination === 'proposition') {
                $this->addFlash('success', sprintf('Recette « %s » créée avec succès.', $recette->getDesignation()));
                return $this->redirectToRoute('home', ['open_proposition' => 1, 'selected_recette' => $recette->getId()]);
            }

            $this->addFlash('success', $isNew ? 'Recette créée avec succès.' : 'Recette mise à jour avec succès.');

            $sortParams = [];
            if ($savedSort = ($request->hasSession() ? $request->getSession()->get('recettes_sort') : null)) {
                $sortParams['sort'] = $savedSort;
            }
            if ($savedOrder = ($request->hasSession() ? $request->getSession()->get('recettes_order') : null)) {
                $sortParams['order'] = $savedOrder;
            }

            return $this->redirectToRoute('app_recettes_list', $sortParams);
        }

        $destination = $request->query->get('destination', $request->request->get('destination', ''));

        return $this->render('pages/pageComposant.html.twig', [
            'twig' => 'pages/recette/form',
            'recette' => $recette,
            'form' => $form->createView(),
            'isNew' => $isNew,
            'destination' => $destination,
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
            foreach ($recette->getPhotos() as $p) {
                $recettePhotoService->delete($p);
            }
            $em->remove($recette);
            $em->flush();
            $this->addFlash('success', 'Recette supprimée.');
        }

        $sortParams = [];
        if ($savedSort = ($request->hasSession() ? $request->getSession()->get('recettes_sort') : null)) {
            $sortParams['sort'] = $savedSort;
        }
        if ($savedOrder = ($request->hasSession() ? $request->getSession()->get('recettes_order') : null)) {
            $sortParams['order'] = $savedOrder;
        }

        return $this->redirectToRoute('app_recettes_list', $sortParams);
    }
}
