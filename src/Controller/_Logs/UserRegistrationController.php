<?php

namespace App\Controller\_Logs;

use App\Entity\FamilleInvitation;
use App\Entity\User;
use App\Form\UserRegistrationType;
use App\Repository\FamilleInvitationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class UserRegistrationController extends AbstractController
{
    #[Route('/inscription', name: 'app_register', methods: ['GET', 'POST'])]
    #[Route('/register', name: 'app_register_alias', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        Security $security,
        FamilleInvitationRepository $invitationRepository
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('home');
        }

        $user = new User();
        $form = $this->createForm(UserRegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            $user->setRoles(['ROLE_USER']);

            $em->persist($user);

            // Rattacher les éventuelles invitations en attente pour cet email
            $pendingInvitations = $invitationRepository->findBy([
                'inviteEmail' => strtolower($user->getEmail()),
                'statut' => FamilleInvitation::STATUT_EN_ATTENTE,
            ]);
            foreach ($pendingInvitations as $invitation) {
                $invitation->setInviteUser($user);
            }

            $em->flush();

            $this->addFlash('success', 'Votre compte a été créé avec succès ! Bienvenue sur QuoiManger.');

            try {
                $security->login($user, 'App\Security\ManualAuthenticator');
                return $this->redirectToRoute('home');
            } catch (\Throwable) {
                return $this->redirectToRoute('web_login');
            }
        }

        return $this->render('_logs/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
