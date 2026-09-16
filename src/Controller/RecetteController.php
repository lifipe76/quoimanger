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

class RecetteController extends AbstractController
{
    #[Route('/recettes', name: 'app_recettes_list', methods: ['GET'])]
    public function index(RecetteRepository $recetteRepository): Response
    {
        $recettes = $recetteRepository->findBy([], ['id' => 'DESC']);

        return $this->render('pages/recette/index.html.twig', [
            'recettes' => $recettes,
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

        return $this->render('pages/recette/form.html.twig', [
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
