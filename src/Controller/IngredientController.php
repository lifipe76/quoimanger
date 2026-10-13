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
        RecipeSuggestionService $suggestionService
    ): Response {
        $ingredients = $ingredientRepository->findBy([], ['designation' => 'ASC']);
        $ranks = $suggestionService->calculateIngredientRanks($ingredients);

        return $this->render('pages/pageComposant.html.twig', [
            'twig' => 'pages/ingredient/index',
            'ingredients' => $ingredients,
            'ranks' => $ranks,
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

            return $this->redirectToRoute('app_ingredients_list');
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

        return $this->redirectToRoute('app_ingredients_list');
    }
}
