<?php

namespace App\Entity;

use App\Entity\Traits\DatesTrait;
use App\Repository\FamilleInvitationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FamilleInvitationRepository::class)]
#[ORM\Table(name: 'famille_invitations')]
#[ORM\HasLifecycleCallbacks]
class FamilleInvitation
{
    use DatesTrait;

    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_ACCEPTEE = 'acceptee';
    public const STATUT_REFUSEE = 'refusee';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Famille::class, inversedBy: 'invitations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Famille $famille = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $demandeur = null;

    #[ORM\Column(length: 255)]
    private ?string $inviteEmail = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $inviteUser = null;

    #[ORM\Column(length: 30)]
    private string $statut = self::STATUT_EN_ATTENTE;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFamille(): ?Famille
    {
        return $this->famille;
    }

    public function setFamille(?Famille $famille): static
    {
        $this->famille = $famille;
        return $this;
    }

    public function getDemandeur(): ?User
    {
        return $this->demandeur;
    }

    public function setDemandeur(?User $demandeur): static
    {
        $this->demandeur = $demandeur;
        return $this;
    }

    public function getInviteEmail(): ?string
    {
        return $this->inviteEmail;
    }

    public function setInviteEmail(string $inviteEmail): static
    {
        $this->inviteEmail = strtolower(trim($inviteEmail));
        return $this;
    }

    public function getInviteUser(): ?User
    {
        return $this->inviteUser;
    }

    public function setInviteUser(?User $inviteUser): static
    {
        $this->inviteUser = $inviteUser;
        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function isEnAttente(): bool
    {
        return $this->statut === self::STATUT_EN_ATTENTE;
    }
}
