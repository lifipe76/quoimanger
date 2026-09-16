<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\HttpFoundation\Response;


class ManualAuthenticator extends AbstractAuthenticator
{
    public function supports(Request $request): ?bool
    {
        // Cet authenticator ne doit PAS s’activer automatiquement
        return false;
    }

    public function authenticate(Request $request): Passport
    {
        throw new \LogicException('ManualAuthenticator should not be used this way.');
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, \Symfony\Component\Security\Core\Exception\AuthenticationException $exception): ?Response
    {
        return null;
    }
}
