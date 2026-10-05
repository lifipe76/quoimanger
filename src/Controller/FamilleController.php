<?php

namespace App\Controller;

use App\Entity\Famille;
use App\Entity\FamilleInvitation;
use App\Entity\User;
use App\Repository\FamilleInvitationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class FamilleController extends AbstractController
{
    #[Route('/famille/inviter', name: 'app_famille_inviter', methods: ['POST'])]
    public function inviter(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepository,
        FamilleInvitationRepository $invitationRepository
    ): Response {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('famille_inviter', $token)) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_account_profile', ['tab' => 'famille']);
        }

        $email = strtolower(trim((string) $request->request->get('invite_email')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('danger', 'Veuillez saisir une adresse email valide.');
            return $this->redirectToRoute('app_account_profile', ['tab' => 'famille']);
        }

        if (strtolower($currentUser->getEmail()) === $email) {
            $this->addFlash('danger', 'Vous ne pouvez pas vous inviter vous-même.');
            return $this->redirectToRoute('app_account_profile', ['tab' => 'famille']);
        }

        // Créer la famille si l'utilisateur n'en a pas encore
        $famille = $currentUser->getFamille();
        if (!$famille) {
            $famille = new Famille();
            $nomFamille = $currentUser->getFirstname() ? 'Famille ' . $currentUser->getFirstname() : 'Ma famille';
            $famille->setNom($nomFamille);
            $famille->setCreateur($currentUser);
            $em->persist($famille);
            $currentUser->setFamille($famille);
        } else {
            if (!$famille->isChef($currentUser)) {
                $this->addFlash('danger', 'Seul le chef de famille peut inviter de nouveaux membres.');
                return $this->redirectToRoute('app_account_profile', ['tab' => 'famille']);
            }
        }

        // Vérifier si la personne est déjà membre de la famille
        foreach ($famille->getMembres() as $membre) {
            if (strtolower($membre->getEmail()) === $email) {
                $this->addFlash('danger', 'Cet utilisateur fait déjà partie de votre famille.');
                return $this->redirectToRoute('app_account_profile', ['tab' => 'famille']);
            }
        }

        // Vérifier si une invitation est déjà en cours
        $existing = $invitationRepository->findOneBy([
            'famille' => $famille,
            'inviteEmail' => $email,
            'statut' => FamilleInvitation::STATUT_EN_ATTENTE,
        ]);
        if ($existing) {
            $this->addFlash('danger', 'Une invitation est déjà en cours pour cette adresse email.');
            return $this->redirectToRoute('app_account_profile', ['tab' => 'famille']);
        }

        $targetUser = $userRepository->findOneBy(['email' => $email]);

        $invitation = new FamilleInvitation();
        $invitation->setFamille($famille);
        $invitation->setDemandeur($currentUser);
        $invitation->setInviteEmail($email);
        $invitation->setInviteUser($targetUser);
        $invitation->setStatut(FamilleInvitation::STATUT_EN_ATTENTE);

        $em->persist($invitation);
        $em->flush();

        $this->addFlash('success', sprintf('Invitation envoyée avec succès à %s !', $email));

        return $this->redirectToRoute('app_account_profile', ['tab' => 'famille']);
    }

    #[Route('/famille/invitation/{id<\d+>}/annuler', name: 'app_famille_invitation_annuler', methods: ['POST'])]
    public function annulerInvitation(
        FamilleInvitation $invitation,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('cancel_invitation_' . $invitation->getId(), $token)) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_account_profile', ['tab' => 'famille']);
        }

        if ($invitation->getDemandeur() !== $currentUser && $invitation->getFamille() !== $currentUser->getFamille()) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($invitation);
        $em->flush();

        $this->addFlash('success', 'Invitation annulée.');

        return $this->redirectToRoute('app_account_profile', ['tab' => 'famille']);
    }

    #[Route('/famille/invitation/{id<\d+>}/accepter', name: 'app_famille_invitation_accepter', methods: ['POST'])]
    public function accepterInvitation(
        FamilleInvitation $invitation,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('accept_invitation_' . $invitation->getId(), $token)) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $this->redirectBackOrHome($request);
        }

        // Vérifier que l'invitation concerne bien l'utilisateur courant
        $isRecipient = ($invitation->getInviteUser() === $currentUser)
            || (strtolower($invitation->getInviteEmail()) === strtolower($currentUser->getEmail()));

        if (!$isRecipient) {
            throw $this->createAccessDeniedException('Cette invitation ne vous est pas destinée.');
        }

        // Rejoindre la nouvelle famille
        $currentUser->setFamille($invitation->getFamille());
        $invitation->setInviteUser($currentUser);
        $invitation->setStatut(FamilleInvitation::STATUT_ACCEPTEE);

        $em->flush();

        $this->addFlash('success', sprintf(
            'Félicitations ! Vous avez rejoint la famille « %s ».',
            $invitation->getFamille()->getNom()
        ));

        return $this->redirectBackOrHome($request);
    }

    #[Route('/famille/invitation/{id<\d+>}/refuser', name: 'app_famille_invitation_refuser', methods: ['POST'])]
    public function refuserInvitation(
        FamilleInvitation $invitation,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('refuse_invitation_' . $invitation->getId(), $token)) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $this->redirectBackOrHome($request);
        }

        $isRecipient = ($invitation->getInviteUser() === $currentUser)
            || (strtolower($invitation->getInviteEmail()) === strtolower($currentUser->getEmail()));

        if (!$isRecipient) {
            throw $this->createAccessDeniedException('Cette invitation ne vous est pas destinée.');
        }

        $invitation->setStatut(FamilleInvitation::STATUT_REFUSEE);
        $em->flush();

        $this->addFlash('info', 'Invitation refusée.');

        return $this->redirectBackOrHome($request);
    }

    #[Route('/famille/renommer', name: 'app_famille_renommer', methods: ['POST'])]
    public function renommer(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $famille = $currentUser->getFamille();

        if (!$famille) {
            $this->addFlash('danger', 'Vous ne faites partie d\'aucune famille.');
            return $this->redirectToRoute('app_account_profile', ['tab' => 'famille']);
        }

        if (!$famille->isChef($currentUser)) {
            $this->addFlash('danger', 'Seul le chef de famille peut renommer la famille.');
            return $this->redirectToRoute('app_account_profile', ['tab' => 'famille']);
        }

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('famille_renommer', $token)) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_account_profile', ['tab' => 'famille']);
        }

        $nom = trim((string) $request->request->get('nom'));
        if ($nom !== '') {
            $famille->setNom($nom);
            $em->flush();
            $this->addFlash('success', 'Nom de famille mis à jour.');
        }

        return $this->redirectToRoute('app_account_profile', ['tab' => 'famille']);
    }

    #[Route('/famille/quitter', name: 'app_famille_quitter', methods: ['POST'])]
    public function quitter(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $famille = $currentUser->getFamille();

        if (!$famille) {
            return $this->redirectToRoute('app_account_profile', ['tab' => 'famille']);
        }

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('quitter_famille', $token)) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_account_profile', ['tab' => 'famille']);
        }

        $isChef = $famille->isChef($currentUser);
        $currentUser->setFamille(null);

        // Si le chef quitte la famille, transférer le rôle de chef à un autre membre si disponible
        if ($isChef) {
            $remaining = $famille->getMembres();
            $nextChef = $remaining->first();
            $famille->setCreateur($nextChef ?: null);
        }

        // Si la famille n'a plus aucun membre, la nettoyer
        if ($famille->getMembres()->count() === 0) {
            $em->remove($famille);
        }

        $em->flush();

        $this->addFlash('success', 'Vous avez quitté votre famille.');

        return $this->redirectToRoute('app_account_profile', ['tab' => 'famille']);
    }

    private function redirectBackOrHome(Request $request): Response
    {
        $referer = $request->headers->get('referer');
        if ($referer && !str_contains($referer, '/connexion')) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('home');
    }
}
