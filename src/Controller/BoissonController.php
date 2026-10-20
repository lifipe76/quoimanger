<?php

namespace App\Controller;

use App\Entity\Boisson;
use App\Form\BoissonType;
use App\Repository\BoissonRepository;
use App\Service\BoissonPhotoService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class BoissonController extends AbstractController
{
    #[Route('/boissons', name: 'app_boissons_list', methods: ['GET'])]
    public function index(BoissonRepository $boissonRepository, Request $request): Response
    {
        if ($request->hasSession()) {
            $request->getSession()->set('app_mode', 'quoimanger');
        }
        $boissons = $boissonRepository->findAllOrdered();

        return $this->render('pages/pageComposant.html.twig', [
            'twig' => 'pages/boisson/index',
            'boissons' => $boissons,
        ]);
    }

    #[Route('/boisson', name: 'app_boisson_create', methods: ['GET', 'POST'])]
    #[Route('/boisson/{id<\d+>}', name: 'app_boisson_edit', methods: ['GET', 'POST'])]
    public function edit(
        ?Boisson $boisson,
        Request $request,
        EntityManagerInterface $em,
        BoissonPhotoService $photoService
    ): Response {
        $isNew = false;
        if (!$boisson) {
            $boisson = new Boisson();
            $isNew = true;
        }

        $form = $this->createForm(BoissonType::class, $boisson);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($request->request->get('delete_photo') === '1') {
                foreach ($boisson->getPhotos() as $p) {
                    $photoService->delete($p);
                }
                $boisson->setPhotos([]);
            } elseif ($request->request->has('delete_photos')) {
                $deletedPhotos = (array) $request->request->all('delete_photos');
                foreach ($deletedPhotos as $p) {
                    if (is_string($p) && $p !== '') {
                        $photoService->delete($p);
                        $boisson->removePhoto($p);
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
                        $boisson->addPhoto($filename);
                    } catch (\Exception $e) {
                        $this->addFlash('warning', 'La photo n\'a pas pu être enregistrée : ' . $e->getMessage());
                    }
                }
            }

            $em->persist($boisson);
            $em->flush();

            $this->addFlash('success', $isNew ? 'Boisson créée avec succès.' : 'Boisson mise à jour avec succès.');

            return $this->redirectToRoute('app_boissons_list');
        }

        return $this->render('pages/pageComposant.html.twig', [
            'twig' => 'pages/boisson/form',
            'boisson' => $boisson,
            'form' => $form->createView(),
            'isNew' => $isNew,
        ]);
    }

    #[Route('/boisson/{id<\d+>}/delete', name: 'app_boisson_delete', methods: ['POST'])]
    public function delete(
        Boisson $boisson,
        Request $request,
        EntityManagerInterface $em,
        BoissonPhotoService $photoService
    ): Response {
        if ($this->isCsrfTokenValid('delete_boisson_' . $boisson->getId(), (string) $request->request->get('_token'))) {
            foreach ($boisson->getPhotos() as $p) {
                $photoService->delete($p);
            }
            $em->remove($boisson);
            $em->flush();

            $this->addFlash('success', 'Boisson supprimée avec succès.');
        } else {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
        }

        return $this->redirectToRoute('app_boissons_list');
    }
}
