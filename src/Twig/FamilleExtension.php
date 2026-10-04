<?php

namespace App\Twig;

use App\Entity\FamilleInvitation;
use App\Entity\User;
use App\Repository\FamilleInvitationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class FamilleExtension extends AbstractExtension
{
    public function __construct(
        private Security $security,
        private FamilleInvitationRepository $invitationRepository
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('pending_family_invitations', [$this, 'getPendingInvitations']),
        ];
    }

    /**
     * @return FamilleInvitation[]
     */
    public function getPendingInvitations(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        return $this->invitationRepository->findPendingForUser($user);
    }
}
