<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\ActionRealisation;
use App\Entity\User;
use App\Repository\ActionRealisationRepository;
use App\Repository\ActionRepository;
use App\Service\ActionPhotoService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class QuoiFaireHomeController extends AbstractController
{
    #[Route('/quoifaire', name: 'quoifaire_home', methods: ['GET'])]
    public function index(
        ActionRealisationRepository $realisationRepository,
        ActionRepository $actionRepository,
        Request $request
    ): Response {
        $session = $request->hasSession() ? $request->getSession() : null;
        $session?->set('app_mode', 'quoifaire');

        $timeline = $realisationRepository->findTimeline();
        $actions = $actionRepository->findAllOrderedByLastRealisation();

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        $famille = $currentUser?->getFamille();
        $isChef = $famille ? $famille->isChef($currentUser) : true;

        if ($famille && !$isChef) {
            $timeline = array_values(array_filter(
                $timeline,
                fn(ActionRealisation $r) => $currentUser !== null && $r->hasParticipant($currentUser)
            ));
        }

        // Regroupement par jour
        $timelineByDay = [];
        foreach ($timeline as $item) {
            $dayKey = $item->getRealiseAt()?->format('Y-m-d') ?? 'sans-date';
            $timelineByDay[$dayKey][] = $item;
        }

        $defaultMoment = 'matin';
        $familleMembres = $famille?->getMembres() ?? [];

        return $this->render('pages/pageComposant.html.twig', [
            'twig' => 'pages/quoifaire/home',
            'timeline' => $timeline,
            'timelineByDay' => $timelineByDay,
            'defaultMoment' => $defaultMoment,
            'actions' => $actions,
            'familleMembres' => $familleMembres,
        ]);
    }

    #[Route('/action-realisation/add', name: 'app_action_realisation_add', methods: ['POST'])]
    public function addRealisation(
        Request $request,
        ActionRepository $actionRepository,
        EntityManagerInterface $em,
        ActionPhotoService $photoService
    ): Response {
        if (!$this->isCsrfTokenValid('add_action_realisation', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('quoifaire_home');
        }

        $actionId = $request->request->getInt('action_id');
        $action = $actionRepository->find($actionId);
        if (!$action) {
            $this->addFlash('error', 'Action introuvable.');
            return $this->redirectToRoute('quoifaire_home');
        }

        $dateStr = (string) $request->request->get('date', 'now');
        try {
            $realiseAt = new \DateTimeImmutable($dateStr);
        } catch (\Exception $e) {
            $realiseAt = new \DateTimeImmutable('today');
        }

        $moment = (string) $request->request->get('moment', 'matin');
        $complement = trim((string) $request->request->get('complement', ''));

        $realisation = new ActionRealisation();
        $realisation->setAction($action);
        $realisation->setRealiseAt($realiseAt);
        $realisation->setMoment($moment);
        if ($complement !== '') {
            $realisation->setComplement($complement);
        }

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        if ($currentUser) {
            $realisation->addParticipant($currentUser);
        }

        // Upload de photo optionnelle
        $photoFiles = $request->files->all('photos');
        if (empty($photoFiles)) {
            $rawPhoto = $request->files->get('photo');
            if ($rawPhoto instanceof UploadedFile) {
                $photoFiles = [$rawPhoto];
            }
        }

        foreach ($photoFiles as $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                try {
                    $filename = $photoService->upload($file);
                    $realisation->addPhoto($filename);
                } catch (\Exception $e) {
                    $this->addFlash('warning', 'La photo n\'a pas pu être enregistrée : ' . $e->getMessage());
                }
            }
        }

        $em->persist($realisation);
        $em->flush();

        $this->addFlash('success', sprintf('Action « %s » enregistrée dans votre fil QuoiFaire.', $action->getDesignation()));
        return $this->redirectToRoute('quoifaire_home');
    }

    #[Route('/action-realisation/{id<\d+>}/update-date', name: 'app_action_realisation_update_date', methods: ['POST'])]
    public function updateDate(
        ActionRealisation $realisation,
        Request $request,
        EntityManagerInterface $em,
        ActionPhotoService $photoService
    ): Response {
        if (!$this->isCsrfTokenValid('update_action_realisation_date_' . $realisation->getId(), (string) $request->request->get('_token'))) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => false, 'message' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
            }
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('quoifaire_home');
        }

        $dateStr = (string) $request->request->get('date');
        $moment = (string) $request->request->get('moment');
        $complement = $request->request->has('complement') ? trim((string) $request->request->get('complement')) : null;

        if ($dateStr) {
            try {
                $realisation->setRealiseAt(new \DateTimeImmutable($dateStr));
            } catch (\Exception $e) {
                // Keep existing date
            }
        }

        if ($moment) {
            $realisation->setMoment($moment);
        }

        if ($complement !== null) {
            $realisation->setComplement($complement !== '' ? $complement : null);
        }

        // Participants
        if ($request->request->has('participants')) {
            $participantIds = (array) $request->request->all('participants');
            $userRepo = $em->getRepository(User::class);
            $newParticipants = [];
            foreach ($participantIds as $pId) {
                $u = $userRepo->find((int) $pId);
                if ($u) {
                    $newParticipants[] = $u;
                }
            }
            foreach ($realisation->getParticipants() as $p) {
                if (!in_array($p, $newParticipants, true)) {
                    $realisation->removeParticipant($p);
                }
            }
            foreach ($newParticipants as $p) {
                $realisation->addParticipant($p);
            }
        }

        // Suppression photos
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

        // Upload photos
        $photoFiles = $request->files->all('photos');
        if (empty($photoFiles)) {
            $rawPhoto = $request->files->get('photo');
            if ($rawPhoto instanceof UploadedFile) {
                $photoFiles = [$rawPhoto];
            }
        }

        foreach ($photoFiles as $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                try {
                    $filename = $photoService->upload($file);
                    $realisation->addPhoto($filename);
                } catch (\Exception $e) {
                    $this->addFlash('warning', 'La photo n\'a pas pu être enregistrée.');
                }
            }
        }

        $em->flush();

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => true]);
        }

        $this->addFlash('success', 'Action mise à jour avec succès.');
        return $this->redirectToRoute('quoifaire_home');
    }

    #[Route('/action-realisation/{id<\d+>}/delete', name: 'app_action_realisation_delete', methods: ['POST'])]
    public function deleteRealisation(
        ActionRealisation $realisation,
        Request $request,
        EntityManagerInterface $em,
        ActionPhotoService $photoService
    ): Response {
        if (!$this->isCsrfTokenValid('delete_action_realisation_' . $realisation->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('quoifaire_home');
        }

        foreach ($realisation->getPhotos() as $p) {
            $photoService->delete($p);
        }

        $em->remove($realisation);
        $em->flush();

        $this->addFlash('success', 'Action retirée du fil.');
        return $this->redirectToRoute('quoifaire_home');
    }
}
