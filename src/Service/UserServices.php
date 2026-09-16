<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\HttpFoundation\RequestStack;

class UserServices
{
    public function __construct(
        private RequestStack $requestStack,
    ) {}

    public function creationUser(
        User|null $user,
        array $initialData,
    ): User {

        return $user;
    }

    public function creationUserForm(
        User $user,
        mixed $form,
    ) {

        $form->handleRequest($this->requestStack->getCurrentRequest());

        if ($form->isSubmitted() && $form->isValid()) {

            /** @var string $plainPassword */

            $type = $form->get('type')->getData();

            $initialData = [
                'email' => $form->get('email')->getData(),
                'password' => $form->get('plainPassword')->getData(),
            ];

            $this->creationUser(null, $initialData);
        }
    }
}
