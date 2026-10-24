<?php

namespace App\Controller;

use App\Entity\Action;
use App\Form\ActionType;
use App\Repository\ActionRepository;
use App\Service\ActionPhotoService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ActionController extends AbstractController
{
    #[Route('/actions', name: 'app_actions_list', methods: ['GET'])]
    public function index(
        ActionRepository $actionRepository,
        Request $request
    ): Response {
        $session = $request->hasSession() ? $request->getSession() : null;
        $session?->set('app_mode', 'quoifaire');

        $hasSortInQuery = $request->query->has('sort');
        $hasOrderInQuery = $request->query->has('order');

        if ($hasSortInQuery) {
            $sort = (string) $request->query->get('sort');
            $session?->set('actions_sort', $sort);
        } else {
            $sort = (string) ($session?->get('actions_sort') ?? 'date');
        }

        $defaultOrder = match ($sort) {
            'alpha', 'echeance' => 'asc',
            default => 'desc', // 'date', 'count'
        };

        if ($hasOrderInQuery) {
            $order = strtolower((string) $request->query->get('order', ''));
            if (!in_array($order, ['asc', 'desc'], true)) {
                $order = $defaultOrder;
            }
            $session?->set('actions_order', $order);
        } elseif ($hasSortInQuery) {
            $order = $defaultOrder;
            $session?->set('actions_order', $order);
        } else {
            $order = (string) ($session?->get('actions_order') ?? $defaultOrder);
            if (!in_array($order, ['asc', 'desc'], true)) {
                $order = $defaultOrder;
            }
        }

        $allActions = $actionRepository->findAll();

        usort($allActions, function (Action $a, Action $b) use ($sort, $order) {
            $isAsc = ($order === 'asc');

            if ($sort === 'date') {
                $dateA = $a->getLastRealiseAt();
                $dateB = $b->getLastRealiseAt();
                if ($dateA == $dateB) {
                    $cmp = strcasecmp($a->getDesignation() ?? '', $b->getDesignation() ?? '');
                    return $isAsc ? $cmp : -$cmp;
                }
                if ($dateA === null) return 1;
                if ($dateB === null) return -1;
                return $isAsc ? ($dateA <=> $dateB) : ($dateB <=> $dateA);
            } elseif ($sort === 'echeance') {
                $infoA = $a->getEcheanceInfo();
                $infoB = $b->getEcheanceInfo();
                if ($infoA === null && $infoB === null) {
                    $cmp = strcasecmp($a->getDesignation() ?? '', $b->getDesignation() ?? '');
                    return $isAsc ? $cmp : -$cmp;
                }
                if ($infoA === null) return 1;
                if ($infoB === null) return -1;
                $diffA = $infoA['diffDays'];
                $diffB = $infoB['diffDays'];
                if ($diffA === $diffB) {
                    $cmp = strcasecmp($a->getDesignation() ?? '', $b->getDesignation() ?? '');
                    return $isAsc ? $cmp : -$cmp;
                }
                return $isAsc ? ($diffA <=> $diffB) : ($diffB <=> $diffA);
            } elseif ($sort === 'count') {
                $countA = $a->getRealisationsCount();
                $countB = $b->getRealisationsCount();
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
            'twig' => 'pages/action/index',
            'actions' => $allActions,
            'currentSort' => $sort,
            'currentOrder' => $order,
        ]);
    }

    #[Route('/action', name: 'app_action_create', methods: ['GET', 'POST'])]
    #[Route('/action/{id<\d+>}', name: 'app_action_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        EntityManagerInterface $em,
        ActionPhotoService $photoService,
        ?Action $action = null
    ): Response {
        $session = $request->hasSession() ? $request->getSession() : null;
        $session?->set('app_mode', 'quoifaire');

        $isNew = false;
        if (!$action) {
            $action = new Action();
            $isNew = true;
        }

        $form = $this->createForm(ActionType::class, $action);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($request->request->get('delete_photo') === '1') {
                foreach ($action->getPhotos() as $p) {
                    $photoService->delete($p);
                }
                $action->setPhotos([]);
            } elseif ($request->request->has('delete_photos')) {
                $deletedPhotos = (array) $request->request->all('delete_photos');
                foreach ($deletedPhotos as $p) {
                    if (is_string($p) && $p !== '') {
                        $photoService->delete($p);
                        $action->removePhoto($p);
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
                        $action->addPhoto($filename);
                    } catch (\Exception $e) {
                        $this->addFlash('warning', 'La photo n\'a pas pu être enregistrée : ' . $e->getMessage());
                    }
                }
            }

            $em->persist($action);
            $em->flush();

            $destination = $request->query->get('destination', $request->request->get('destination', ''));
            if ($destination === 'quoifaire') {
                $this->addFlash('success', sprintf('Action « %s » créée avec succès.', $action->getDesignation()));
                return $this->redirectToRoute('quoifaire_home', ['open_action' => 1, 'selected_action' => $action->getId()]);
            }

            $this->addFlash('success', $isNew ? 'Action créée avec succès.' : 'Action mise à jour avec succès.');

            $sortParams = [];
            if ($savedSort = ($request->hasSession() ? $request->getSession()->get('actions_sort') : null)) {
                $sortParams['sort'] = $savedSort;
            }
            if ($savedOrder = ($request->hasSession() ? $request->getSession()->get('actions_order') : null)) {
                $sortParams['order'] = $savedOrder;
            }

            return $this->redirectToRoute('app_actions_list', $sortParams);
        }

        return $this->render('pages/pageComposant.html.twig', [
            'twig' => 'pages/action/form',
            'action' => $action,
            'form' => $form->createView(),
            'isNew' => $isNew,
            'destination' => $request->query->get('destination', ''),
        ]);
    }

    #[Route('/action/{id<\d+>}/delete', name: 'app_action_delete', methods: ['POST'])]
    public function delete(
        Action $action,
        Request $request,
        EntityManagerInterface $em,
        ActionPhotoService $photoService
    ): Response {
        if ($this->isCsrfTokenValid('delete_action_' . $action->getId(), (string) $request->request->get('_token'))) {
            foreach ($action->getPhotos() as $p) {
                $photoService->delete($p);
            }
            $em->remove($action);
            $em->flush();
            $this->addFlash('success', 'Action supprimée.');
        }

        $sortParams = [];
        if ($savedSort = ($request->hasSession() ? $request->getSession()->get('actions_sort') : null)) {
            $sortParams['sort'] = $savedSort;
        }
        if ($savedOrder = ($request->hasSession() ? $request->getSession()->get('actions_order') : null)) {
            $sortParams['order'] = $savedOrder;
        }

        return $this->redirectToRoute('app_actions_list', $sortParams);
    }
}
