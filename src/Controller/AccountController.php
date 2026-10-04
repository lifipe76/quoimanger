<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class AccountController extends AbstractController
{
    #[Route('/compte', name: 'app_account_profile', methods: ['GET', 'POST'])]
    public function profile(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        \App\Repository\FamilleInvitationRepository $invitationRepository
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(UserProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = $form->get('newPassword')->getData();
            if (!empty($newPassword)) {
                $user->setPassword($hasher->hashPassword($user, $newPassword));
            }

            $em->flush();

            $this->addFlash('success', 'Vos informations de compte ont été mises à jour avec succès.');

            return $this->redirectToRoute('app_account_profile');
        }

        $activeTab = $request->query->get('tab', 'profil');
        if (!in_array($activeTab, ['profil', 'famille'], true)) {
            $activeTab = 'profil';
        }

        $sentInvitations = [];
        if ($user->getFamille()) {
            $sentInvitations = $invitationRepository->findBy([
                'famille' => $user->getFamille(),
                'statut' => \App\Entity\FamilleInvitation::STATUT_EN_ATTENTE,
            ]);
        }

        return $this->render('pages/pageComposant.html.twig', [
            'twig' => 'pages/account/profile',
            'form' => $form,
            'user' => $user,
            'famille' => $user->getFamille(),
            'sentInvitations' => $sentInvitations,
            'activeTab' => $activeTab,
        ]);
    }
}
