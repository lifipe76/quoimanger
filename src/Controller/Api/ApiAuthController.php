<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\LoginServices;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api', name: 'api_')]
class ApiAuthController extends AbstractController
{
    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        LoginServices $loginServices
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) {
            return new JsonResponse([
                'error' => 'Email et mot de passe requis.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user || !$passwordHasher->isPasswordValid($user, $password)) {
            return new JsonResponse([
                'error' => 'Identifiants invalides.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $token = $loginServices->AppCreateToken($user);

        return new JsonResponse([
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'prenom' => $user->getFirstname(),
                'nom' => $user->getLastname(),
            ]
        ], Response::HTTP_OK);
    }

    #[Route('/forgot-password', name: 'forgot_password', methods: ['POST'])]
    public function forgotPassword(
        Request $request,
        LoginServices $loginServices
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];
        $email = trim($data['email'] ?? '');

        if (empty($email)) {
            return new JsonResponse([
                'error' => 'Veuillez saisir votre adresse email.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Even if user not found, don't leak information
        $loginServices->processSendingPasswordResetEmail($email);

        return new JsonResponse([
            'message' => 'Si un compte existe pour cet email, vous recevrez les instructions de réinitialisation.'
        ], Response::HTTP_OK);
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'prenom' => $user->getFirstname(),
            'nom' => $user->getLastname(),
        ], Response::HTTP_OK);
    }

    #[Route('/me', name: 'me_update', methods: ['PUT', 'PATCH'])]
    public function updateMe(
        Request $request,
        #[CurrentUser] ?User $user,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['prenom'])) {
            $user->setFirstname(trim($data['prenom']));
        }
        if (isset($data['nom'])) {
            $user->setLastname(trim($data['nom']));
        }
        if (!empty($data['password'])) {
            $user->setPassword($passwordHasher->hashPassword($user, $data['password']));
        }

        $em->flush();

        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'prenom' => $user->getFirstname(),
            'nom' => $user->getLastname(),
        ], Response::HTTP_OK);
    }
}
