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
        RecetteRepository $recetteRepository,
        \App\Service\RecipeSuggestionService $suggestionService
    ): Response {
        $timeline = $realisationRepository->findTimeline();
        $recettes = $recetteRepository->findAllOrderedByLastRealisation();
        $ranks = $this->calculateRecipeRanks($recettes);
        $suggestion = $suggestionService->getSuggestion($recettes);

        /** @var \App\Entity\User|null $currentUser */
        $currentUser = $this->getUser();
        $famille = $currentUser?->getFamille();
        $isChef = $famille ? $famille->isChef($currentUser) : true;

        // Pour les membres non-chefs, afficher les repas de toute la famille (isPourTous)
        // et ceux où le membre connecté participe, mais masquer ceux réservés à d'autres membres
        if ($famille && !$isChef) {
            $timeline = array_values(array_filter(
                $timeline,
                fn(RecetteRealisation $r) => $currentUser !== null && $r->hasParticipant($currentUser)
            ));
        }

        // Regroupement de la timeline par jour (format Y-m-d)
        $timelineByDay = [];
        foreach ($timeline as $item) {
            $dayKey = $item->getRealiseAt()?->format('Y-m-d') ?? 'sans-date';
            $timelineByDay[$dayKey][] = $item;
        }

        // Par défaut, sélectionner la bulle Soir
        $defaultMoment = 'soir';

        $familleMembres = $famille?->getMembres() ?? [];

        return $this->render('pages/pageComposant.html.twig', [
            'twig' => 'pages/home',
            'timeline' => $timeline,
            'timelineByDay' => $timelineByDay,
            'defaultMoment' => $defaultMoment,
            'recettes' => $recettes,
            'ranks' => $ranks,
            'familleMembres' => $familleMembres,
            'suggestion' => $suggestion,
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

            if ($request->request->has('has_participants_field')) {
                $participantIds = (array) $request->request->all('participants');
                $realisation->clearParticipants();
                if (!empty($participantIds)) {
                    $participants = $em->getRepository(\App\Entity\User::class)->findBy(['id' => $participantIds]);
                    foreach ($participants as $p) {
                        $realisation->addParticipant($p);
                    }
                }
            }

            if ($request->request->has('notes')) {
                /** @var \App\Entity\User|null $currentUser */
                $currentUser = $this->getUser();
                $notesData = (array) $request->request->all('notes');
                $noteRepo = $em->getRepository(\App\Entity\RealisationNote::class);
                foreach ($notesData as $userId => $noteVal) {
                    $uid = (int) $userId;
                    // L'utilisateur connecté ne peut modifier que sa propre note
                    if ($currentUser && $uid !== (int) $currentUser->getId()) {
                        continue;
                    }
                    $val = (int) $noteVal;
                    $member = $em->getRepository(\App\Entity\User::class)->find($uid);
                    if ($member) {
                        $existingNote = $noteRepo->findOneBy([
                            'realisation' => $realisation,
                            'user' => $member,
                        ]);
                        if ($val >= 1 && $val <= 5) {
                            if (!$existingNote) {
                                $existingNote = new \App\Entity\RealisationNote();
                                $existingNote->setRealisation($realisation);
                                $existingNote->setUser($member);
                                $em->persist($existingNote);
                            }
                            $existingNote->setNote($val);
                        } elseif ($val === 0 && $existingNote) {
                            $em->remove($existingNote);
                        }
                    }
                }
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

    #[Route('/realisation/{id<\d+>}/rate', name: 'app_realisation_rate', methods: ['POST'])]
    public function rateRealisation(
        RecetteRealisation $realisation,
        Request $request,
        EntityManagerInterface $em,
        \App\Repository\UserRepository $userRepository,
        \App\Repository\RealisationNoteRepository $noteRepository
    ): Response {
        /** @var \App\Entity\User $currentUser */
        $currentUser = $this->getUser();

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('rate_realisation_' . $realisation->getId(), $token)) {
            if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Accept', ''), 'application/json')) {
                return new JsonResponse(['error' => 'Jeton CSRF invalide.'], Response::HTTP_FORBIDDEN);
            }
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('home');
        }

        // Utilisateur cible : l'utilisateur connecté uniquement
        $targetUser = $currentUser;

        $noteValue = (int) $request->request->get('note', 0);

        $existingNote = $noteRepository->findOneBy([
            'realisation' => $realisation,
            'user' => $targetUser,
        ]);

        if ($noteValue <= 0) {
            if ($existingNote) {
                $em->remove($existingNote);
                $em->flush();
            }
        } else {
            $noteValue = max(1, min(5, $noteValue));
            if (!$existingNote) {
                $existingNote = new \App\Entity\RealisationNote();
                $existingNote->setRealisation($realisation);
                $existingNote->setUser($targetUser);
                $em->persist($existingNote);
            }
            $existingNote->setNote($noteValue);
            $em->flush();
        }

        if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Accept', ''), 'application/json')) {
            return new JsonResponse([
                'success' => true,
                'realisationId' => $realisation->getId(),
                'userNote' => $realisation->getNoteForUser($currentUser),
                'recipeAverage' => $realisation->getRecette()->getAverageNote(),
                'recipeCount' => $realisation->getRecette()->getNotesCount(),
            ]);
        }

        $this->addFlash('success', 'Votre note a bien été enregistrée.');

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
