<?php

namespace App\Entity;

use App\Repository\RepasVoteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RepasVoteRepository::class)]
#[ORM\Table(name: 'repas_votes')]
#[ORM\UniqueConstraint(name: 'unique_user_proposition_vote', columns: ['proposition_id', 'user_id'])]
class RepasVote
{
    public const CHOIX_POUR = 'pour';
    public const CHOIX_CONTRE = 'contre';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RepasProposition::class, inversedBy: 'votes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?RepasProposition $proposition = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 20)]
    private string $choix = self::CHOIX_POUR;

    #[ORM\Column]
    private \DateTimeImmutable $votedAt;

    public function __construct()
    {
        $this->votedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProposition(): ?RepasProposition
    {
        return $this->proposition;
    }

    public function setProposition(?RepasProposition $proposition): static
    {
        $this->proposition = $proposition;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getChoix(): string
    {
        return $this->choix;
    }

    public function setChoix(string $choix): static
    {
        $this->choix = $choix;
        return $this;
    }

    public function getVotedAt(): \DateTimeImmutable
    {
        return $this->votedAt;
    }

    public function setVotedAt(\DateTimeImmutable $votedAt): static
    {
        $this->votedAt = $votedAt;
        return $this;
    }
}
