<?php

namespace App\Controller;

use App\Entity\Ingredient;
use App\Form\IngredientType;
use App\Repository\IngredientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class IngredientController extends AbstractController
{
    #[Route('/ingredients', name: 'app_ingredients_list', methods: ['GET'])]
    public function index(IngredientRepository $ingredientRepository): Response
    {
        $ingredients = $ingredientRepository->findBy([], ['designation' => 'ASC']);

        return $this->render('pages/ingredient/index.html.twig', [
            'ingredients' => $ingredients,
        ]);
    }

    #[Route('/ingredient', name: 'app_ingredient_create', methods: ['GET', 'POST'])]
    #[Route('/ingredient/{id<\d+>}', name: 'app_ingredient_edit', methods: ['GET', 'POST'])]
    public function edit(?Ingredient $ingredient, Request $request, EntityManagerInterface $em): Response
    {
        $isNew = false;
        if (!$ingredient) {
            $ingredient = new Ingredient();
            $isNew = true;
        }

        $form = $this->createForm(IngredientType::class, $ingredient);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($ingredient);
            $em->flush();

            $this->addFlash('success', $isNew ? 'Ingrédient créé avec succès.' : 'Ingrédient mis à jour avec succès.');

            return $this->redirectToRoute('app_ingredients_list');
        }

        return $this->render('pages/ingredient/form.html.twig', [
            'ingredient' => $ingredient,
            'form' => $form->createView(),
            'isNew' => $isNew,
        ]);
    }

    #[Route('/ingredient/{id<\d+>}/delete', name: 'app_ingredient_delete', methods: ['POST'])]
    public function delete(Ingredient $ingredient, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_ingredient_' . $ingredient->getId(), (string) $request->request->get('_token'))) {
            $em->remove($ingredient);
            $em->flush();
            $this->addFlash('success', 'Ingrédient supprimé.');
        }

        return $this->redirectToRoute('app_ingredients_list');
    }
}
