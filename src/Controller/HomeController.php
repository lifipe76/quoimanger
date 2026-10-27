<?php

namespace App\Controller;

use App\Entity\Boisson;
use App\Entity\Recette;
use App\Entity\RecetteRealisation;
use App\Entity\RepasBoisson;
use App\Repository\BoissonRepository;
use App\Repository\RecetteRealisationRepository;
use App\Repository\RecetteRepository;
use App\Service\RepasPhotoService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
        BoissonRepository $boissonRepository,
        \App\Repository\FrigoRepository $frigoRepository,
        \App\Service\RecipeSuggestionService $suggestionService,
        Request $request
    ): Response {
        if ($request->hasSession()) {
            $request->getSession()->set('app_mode', 'quoimanger');
        }
        $timeline = $realisationRepository->findTimeline();
        $recettes = $recetteRepository->findAllOrderedByLastRealisation();
        $ranks = $this->calculateRecipeRanks($recettes);
        $suggestion = $suggestionService->getSuggestion($recettes);

        // Calcul des scores de probabilité (ancienneté, habitude jour de la semaine, favoris)
        $today = new \DateTimeImmutable('today');
        $probabilityScores = [];
        foreach ($recettes as $r) {
            if ($r->getId() !== null) {
                $probabilityScores[$r->getId()] = $suggestionService->calculateScore($r, $today, $ranks);
            }
        }
        // Trier les recettes par probabilité décroissante
        $recettesProposees = $suggestionService->sortRecettesByProbability($recettes, $today, $ranks);

        /** @var \App\Entity\User|null $currentUser */
        $currentUser = $this->getUser();
        $famille = $currentUser?->getFamille();
        $isChef = $famille ? $famille->isChef($currentUser) : true;

        $frigoItems = $frigoRepository->findForUserOrFamille($currentUser, $famille);
        $frigoRecetteIds = $frigoRepository->findRecetteIdsForUserOrFamille($currentUser, $famille);

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
            'recettesProposees' => $recettesProposees,
            'probabilityScores' => $probabilityScores,
            'ranks' => $ranks,
            'familleMembres' => $familleMembres,
            'suggestion' => $suggestion,
            'boissons' => $boissonRepository->findAllOrdered(),
            'frigoItems' => $frigoItems,
            'frigoRecetteIds' => $frigoRecetteIds,
        ]);
    }

    #[Route('/realisation/add', name: 'app_realisation_add', methods: ['POST'])]
    public function addRealisation(
        Request $request,
        RecetteRepository $recetteRepository,
        \App\Repository\FrigoRepository $frigoRepository,
        EntityManagerInterface $em,
        RepasPhotoService $photoService
    ): Response {
        $data = json_decode($request->getContent(), true) ?? [];
        $recetteId = (int) ($request->request->get('recette_id') ?? $data['recette_id'] ?? 0);
        $recetteIds = (array) ($request->request->all('recette_ids') ?? $data['recette_ids'] ?? []);
        if (empty($recetteIds) && $recetteId > 0) {
            $recetteIds = [$recetteId];
        }

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

        $realisation = new RecetteRealisation();
        $first = true;
        $order = [];
        foreach ($recetteIds as $rId) {
            $r = $recetteRepository->find((int) $rId);
            if ($r) {
                if ($first) {
                    $realisation->setRecette($r);
                    $first = false;
                }
                $realisation->addRecette($r);
                if ($r->getId()) {
                    $order[] = $r->getId();
                }
            }
        }
        if (!empty($order)) {
            $realisation->setRecettesOrder($order);
        }

        if (!$realisation->getRecette()) {
            if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Accept', ''), 'application/json')) {
                return new JsonResponse(['error' => 'Recette introuvable.'], Response::HTTP_NOT_FOUND);
            }
            $this->addFlash('danger', 'Recette introuvable.');
            return $this->redirectToRoute('home');
        }

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

        $photoFiles = $request->files->all('photos');
        if (empty($photoFiles)) {
            $rawPhoto = $request->files->get('photo');
            if ($rawPhoto instanceof UploadedFile) {
                $photoFiles = [$rawPhoto];
            } elseif (is_array($rawPhoto)) {
                $photoFiles = $rawPhoto;
            }
        }

        foreach ($photoFiles as $photoFile) {
            if ($photoFile instanceof UploadedFile && $photoFile->isValid()) {
                try {
                    $photoFilename = $photoService->upload($photoFile);
                    $realisation->addPhoto($photoFilename);
                } catch (\Exception $e) {
                    $this->addFlash('warning', 'Photo non enregistrée : ' . $e->getMessage());
                }
            }
        }

        if ($request->request->has('has_participants_field')) {
            $participantIds = (array) $request->request->all('participants');
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
            foreach ($notesData as $userId => $noteVal) {
                $uid = (int) $userId;
                if ($currentUser && $uid !== (int) $currentUser->getId()) {
                    continue;
                }
                $val = (int) $noteVal;
                if ($val >= 1 && $val <= 5 && $currentUser) {
                    $note = new \App\Entity\RealisationNote();
                    $note->setRealisation($realisation);
                    $note->setUser($currentUser);
                    $note->setNote($val);
                    $em->persist($note);
                }
            }
        }

        $em->persist($realisation);

        // Si les recettes étaient dans le frigo de l'utilisateur ou de sa famille, on les retire
        /** @var \App\Entity\User|null $currentUser */
        $currentUser = $this->getUser();
        $famille = $currentUser?->getFamille();
        foreach ($realisation->getRecettes() as $rec) {
            if ($rec) {
                $frigoRepository->removeByRecette($rec, $currentUser, $famille);
            }
        }

        $em->flush();

        if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Accept', ''), 'application/json')) {
            return new JsonResponse([
                'success' => true,
                'id' => $realisation->getId(),
                'recette' => $realisation->getDisplayTitle(),
                'moment' => $realisation->getMoment(),
                'realiseAt' => $realisation->getRealiseAt()?->format('d/m/Y'),
                'photo' => $realisation->getPhoto(),
                'photoUrl' => $realisation->getPhotoUrl(),
                'photos' => $realisation->getPhotos(),
                'photoUrls' => $realisation->getPhotoUrls(),
            ]);
        }

        $this->addFlash('success', sprintf('Repas enregistré avec succès : « %s » !', $realisation->getDisplayTitle()));

        return $this->redirectToRoute('home');
    }

    #[Route('/realisation/{id<\d+>}/delete', name: 'app_realisation_delete', methods: ['POST'])]
    public function deleteRealisation(
        RecetteRealisation $realisation,
        Request $request,
        EntityManagerInterface $em,
        RepasPhotoService $photoService
    ): Response {
        if ($this->isCsrfTokenValid('delete_realisation_' . $realisation->getId(), (string) $request->request->get('_token'))) {
            foreach ($realisation->getPhotos() as $p) {
                $photoService->delete($p);
            }
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
        EntityManagerInterface $em,
        RepasPhotoService $photoService
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

            if ($request->request->has('has_recettes_field') || $request->request->has('recette_ids')) {
                $recetteIds = (array) $request->request->all('recette_ids');
                if (!empty($recetteIds)) {
                    $recetteRepo = $em->getRepository(Recette::class);
                    $newRecettes = [];
                    $order = [];
                    foreach ($recetteIds as $rId) {
                        $rec = $recetteRepo->find((int) $rId);
                        if ($rec && !in_array($rec, $newRecettes, true)) {
                            $newRecettes[] = $rec;
                            if ($rec->getId()) {
                                $order[] = $rec->getId();
                            }
                        }
                    }
                    if (!empty($newRecettes)) {
                        $realisation->setRecettes($newRecettes);
                        $realisation->setRecettesOrder($order);
                        $realisation->setRecette($newRecettes[0]);
                    }
                }
            }

            if ($request->request->get('delete_photo') === '1') {
                foreach ($realisation->getPhotos() as $p) {
                    $photoService->delete($p);
                }
                $realisation->setPhotos([]);
            } elseif ($request->request->has('delete_photos')) {
                $deletedPhotos = (array) $request->request->all('delete_photos');
                foreach ($deletedPhotos as $p) {
                    if (is_string($p) && $p !== '') {
                        $photoService->delete($p);
                        $realisation->removePhoto($p);
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

            foreach ($photoFiles as $photoFile) {
                if ($photoFile instanceof UploadedFile && $photoFile->isValid()) {
                    try {
                        $photoFilename = $photoService->upload($photoFile);
                        $realisation->addPhoto($photoFilename);
                    } catch (\Exception $e) {
                        $this->addFlash('warning', 'Photo non enregistrée : ' . $e->getMessage());
                    }
                }
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

            if ($request->request->has('boisson_id')) {
                $boissonId = $request->request->get('boisson_id');
                $dateAchatStr = (string) $request->request->get('boisson_date_achat');
                $boissonNoteVal = (int) $request->request->get('boisson_note');

                $existingRepasBoisson = $realisation->getRepasBoisson();

                if ($boissonId !== null && $boissonId !== '') {
                    $selectedBoisson = $em->getRepository(Boisson::class)->find((int) $boissonId);
                    if ($selectedBoisson) {
                        if (!$existingRepasBoisson) {
                            $existingRepasBoisson = new RepasBoisson();
                            $existingRepasBoisson->setRealisation($realisation);
                            $em->persist($existingRepasBoisson);
                        }
                        $existingRepasBoisson->setBoisson($selectedBoisson);

                        if ($dateAchatStr !== '') {
                            try {
                                $existingRepasBoisson->setDateAchat(new \DateTimeImmutable($dateAchatStr));
                            } catch (\Exception) {
                                $existingRepasBoisson->setDateAchat(null);
                            }
                        } else {
                            $existingRepasBoisson->setDateAchat(null);
                        }

                        if ($boissonNoteVal >= 1 && $boissonNoteVal <= 5) {
                            $existingRepasBoisson->setNote($boissonNoteVal);
                        } else {
                            $existingRepasBoisson->setNote(null);
                        }
                    }
                } elseif ($existingRepasBoisson) {
                    $realisation->removeRepasBoisson($existingRepasBoisson);
                    $em->remove($existingRepasBoisson);
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
                $realisation->getDisplayTitle(),
                $realisation->getComplement()
            ));
        } else {
            $this->addFlash('success', sprintf(
                'Complément retiré pour « %s ».',
                $realisation->getDisplayTitle()
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
                'recipeAverage' => $realisation->getAverageNote(),
                'recipeCount' => $realisation->getNotesCount(),
                'mealAverage' => $realisation->getAverageNote(),
                'mealCount' => $realisation->getNotesCount(),
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
