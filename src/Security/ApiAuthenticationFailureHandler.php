<?php

namespace App\Security;

use App\Repository\UserRepository;
use App\Service\LoginServices;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ApiAuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private LoginServices $loginServices,
        private TranslatorInterface $translator,
    ) {}

    public function onAuthenticationFailure(
        Request $request,
        AuthenticationException $exception,

    ): Response {
        $data = json_decode($request->getContent(), true);

        $email = $data['username'] ?? null;

        if ($email) {

            $user = $this->userRepository->findOneBy(['email' => $email]);

            if ($user && $user->isMigrate() === false) {

                try {
                    $this->loginServices->processSendingPasswordResetEmail($user->getEmail());
                } catch (\Exception $e) {
                }

                return new JsonResponse([
                    'success' => false,
                    'message' => $this->translator->trans("migrate"),
                    'code'    => 'MIGRATION_REQUIRED'
                ], Response::HTTP_FORBIDDEN); // 403 Forbidden
            }
        }

        // Échec classique (mauvais mot de passe ou email inexistant)
        return new JsonResponse([
            'success' => false,
            'message' => 'Identifiants invalides.',
            'error'   => $exception->getMessageKey(),
        ], Response::HTTP_UNAUTHORIZED); // 401 Unauthorized
    }
}
