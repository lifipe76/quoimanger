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

        $famille = $user->getFamille();
        $receivedInvitations = $invitationRepository->findPendingForUser($user);
        $pendingInvitation = !empty($receivedInvitations) ? $receivedInvitations[0] : null;

        $isPendingMember = false;
        // Si l'utilisateur a une invitation en attente pour cette famille ou n'a pas encore validé de famille :
        if ($pendingInvitation) {
            if (!$famille || $famille->getId() === $pendingInvitation->getFamille()->getId()) {
                $famille = $pendingInvitation->getFamille();
                $isPendingMember = true;
            }
        }

        $isChef = ($famille && !$isPendingMember) ? $famille->isChef($user) : false;

        // Récupérer toutes les invitations en attente pour cette famille
        $pendingInvitesForFamille = [];
        if ($famille) {
            $pendingInvitesForFamille = $invitationRepository->findBy([
                'famille' => $famille,
                'statut' => \App\Entity\FamilleInvitation::STATUT_EN_ATTENTE,
            ]);
        }

        $pendingEmails = [];
        $pendingUserIds = [];
        foreach ($pendingInvitesForFamille as $inv) {
            $pendingEmails[] = strtolower($inv->getInviteEmail());
            if ($inv->getInviteUser()) {
                $pendingUserIds[] = $inv->getInviteUser()->getId();
            }
        }

        // Synchroniser automatiquement tout utilisateur ayant une invitation acceptée pour cette famille
        if ($famille) {
            $acceptedInvitations = $invitationRepository->findBy([
                'famille' => $famille,
                'statut' => \App\Entity\FamilleInvitation::STATUT_ACCEPTEE,
            ]);
            $userRepo = $em->getRepository(User::class);
            $hasSynced = false;
            foreach ($acceptedInvitations as $accInv) {
                $accUser = $accInv->getInviteUser();
                if (!$accUser && $accInv->getInviteEmail()) {
                    $accUser = $userRepo->findOneBy(['email' => $accInv->getInviteEmail()]);
                }
                if ($accUser && $accUser->getFamille()?->getId() !== $famille->getId()) {
                    $accUser->setFamille($famille);
                    $accInv->setInviteUser($accUser);
                    $hasSynced = true;
                }
            }
            if ($hasSynced) {
                $em->flush();
                $em->refresh($famille);
            }
        }

        // Membres confirmés uniquement (qui ont réellement accepté et ne sont pas en attente)
        $confirmedMembres = [];
        if ($famille) {
            foreach ($famille->getMembres() as $m) {
                if (in_array($m->getId(), $pendingUserIds, true) || in_array(strtolower($m->getEmail()), $pendingEmails, true)) {
                    continue;
                }
                $confirmedMembres[] = $m;
            }
        }

        $sentInvitations = [];
        if ($famille && $isChef) {
            $sentInvitations = $pendingInvitesForFamille;
        }

        return $this->render('pages/pageComposant.html.twig', [
            'twig' => 'pages/account/profile',
            'form' => $form,
            'user' => $user,
            'famille' => $famille,
            'confirmedMembres' => $confirmedMembres,
            'isChef' => $isChef,
            'isPendingMember' => $isPendingMember,
            'pendingInvitation' => $pendingInvitation,
            'sentInvitations' => $sentInvitations,
            'receivedInvitations' => $receivedInvitations,
            'activeTab' => $activeTab,
        ]);
    }
}
