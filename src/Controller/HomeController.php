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

#[IsGranted('ROLE_USER')]
class HomeController extends AbstractController
{

    #[Route('/', name: 'home', methods: ['GET'])]
    public function home(
        RecetteRealisationRepository $realisationRepository,
        RecetteRepository $recetteRepository
    ): Response {
        $timeline = $realisationRepository->findTimeline();
        $recettes = $recetteRepository->findAllOrderedByLastRealisation();
        $ranks = $this->calculateRecipeRanks($recettes);

        // Regroupement de la timeline par jour (format Y-m-d)
        $timelineByDay = [];
        foreach ($timeline as $item) {
            $dayKey = $item->getRealiseAt()?->format('Y-m-d') ?? 'sans-date';
            $timelineByDay[$dayKey][] = $item;
        }

        // Par défaut, sélectionner la bulle Soir
        $defaultMoment = 'soir';

        return $this->render('pages/home/index.html.twig', [
            'timeline' => $timeline,
            'timelineByDay' => $timelineByDay,
            'defaultMoment' => $defaultMoment,
            'recettes' => $recettes,
            'ranks' => $ranks,
        ]);
    }

    #[Route('/realisation/add', name: 'app_realisation_add', methods: ['POST'])]
    public function addRealisation(
        Request $request,
        RecetteRepository $recetteRepository,
        EntityManagerInterface $em
    ): Response {
        $data = json_decode($request->getContent(), true) ?? [];
        $recetteId = (int) ($request->request->get('recette_id') ?? $data['recette_id'] ?? 0);
        $csrfToken = (string) ($request->request->get('_token') ?? $data['_token'] ?? '');
        $moment = (string) ($request->request->get('moment') ?? $data['moment'] ?? 'soir');
        $dateStr = (string) ($request->request->get('date') ?? $data['date'] ?? '');

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

        if ($dateStr !== '') {
            try {
                $realiseAt = new \DateTimeImmutable($dateStr);
            } catch (\Exception) {
                $realiseAt = new \DateTimeImmutable();
            }
        } else {
            $realiseAt = new \DateTimeImmutable();
        }
        $realisation->setRealiseAt($realiseAt);

        if (in_array(strtolower($moment), ['matin', 'midi', 'soir'], true)) {
            $realisation->setMoment(strtolower($moment));
        } else {
            $realisation->setMoment('soir');
        }

        $em->persist($realisation);
        $em->flush();

        if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Accept', ''), 'application/json')) {
            return new JsonResponse([
                'success' => true,
                'id' => $realisation->getId(),
                'recette' => $recette->getDesignation(),
                'moment' => $realisation->getMoment(),
                'realiseAt' => $realisation->getRealiseAt()?->format('d/m/Y'),
            ]);
        }

        $this->addFlash('success', sprintf('Repas enregistré avec succès : « %s » !', $recette->getDesignation()));

        return $this->redirectToRoute('home');
    }

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

    #[Route('/realisation/{id<\d+>}/update-date', name: 'app_realisation_update_date', methods: ['POST'])]
    public function updateRealisationDate(
        RecetteRealisation $realisation,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('update_realisation_date_' . $realisation->getId(), $token)) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('home');
        }

        $dateStr = (string) $request->request->get('realise_at');
        $moment = (string) $request->request->get('moment');

        if ($dateStr === '') {
            $this->addFlash('danger', 'Veuillez sélectionner une date valide.');
            return $this->redirectToRoute('home');
        }

        try {
            $newDate = new \DateTimeImmutable($dateStr);
            $realisation->setRealiseAt($newDate);
            if (in_array(strtolower($moment), ['matin', 'midi', 'soir'], true)) {
                $realisation->setMoment(strtolower($moment));
            }
            if ($request->request->has('complement')) {
                $complement = $request->request->get('complement');
                $realisation->setComplement($complement !== null ? (string) $complement : null);
            }
            $em->flush();
            $this->addFlash('success', sprintf('Date du repas mise à jour au %s.', $newDate->format('d/m/Y')));
        } catch (\Exception) {
            $this->addFlash('danger', 'Format de date invalide.');
        }

        return $this->redirectToRoute('home');
    }

    #[Route('/realisation/{id}/update-complement', name: 'app_realisation_update_complement', methods: ['POST'])]
    public function updateRealisationComplement(
        int $id,
        Request $request,
        RecetteRealisationRepository $realisationRepository,
        EntityManagerInterface $em
    ): Response {
        $realisation = $realisationRepository->find($id);
        if (!$realisation) {
            throw $this->createNotFoundException('Réalisation introuvable.');
        }

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('update_realisation_complement_' . $realisation->getId(), $token)) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('home');
        }

        $complement = $request->request->get('complement');
        $realisation->setComplement($complement !== null ? (string) $complement : null);
        $em->flush();

        if ($realisation->getComplement()) {
            $this->addFlash('success', sprintf(
                'Complément mis à jour pour « %s » (%s).',
                $realisation->getRecette()->getDesignation(),
                $realisation->getComplement()
            ));
        } else {
            $this->addFlash('success', sprintf(
                'Complément retiré pour « %s ».',
                $realisation->getRecette()->getDesignation()
            ));
        }

        return $this->redirectToRoute('home');
    }

    /**
     * Calcule le classement des recettes par rapport au nombre de réalisations
     *
     * @param Recette[] $recettes
     * @return array<int, int|null> [recetteId => rank]
     */
    private function calculateRecipeRanks(array $recettes): array
    {
        $counts = [];
        foreach ($recettes as $r) {
            if ($r->getId() !== null) {
                $counts[$r->getId()] = $r->getRealisations()->count();
            }
        }

        arsort($counts);

        $ranks = [];
        $currentRank = 0;
        $prevCount = null;
        $position = 0;

        foreach ($counts as $id => $count) {
            $position++;
            if ($count === 0) {
                $ranks[$id] = null;
                continue;
            }
            if ($count !== $prevCount) {
                $currentRank = $position;
                $prevCount = $count;
            }
            $ranks[$id] = $currentRank;
        }

        return $ranks;
    }
}
