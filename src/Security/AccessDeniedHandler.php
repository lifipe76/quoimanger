<?php


namespace App\Security;


use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(
        private RouterInterface $router,
        private TokenStorageInterface $tokenStorage
    ) {}

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        $this->tokenStorage->setToken(null);

        $request->getSession()->invalidate();

        return new RedirectResponse(
            $this->router->generate('web_login')
        );
    }
}
