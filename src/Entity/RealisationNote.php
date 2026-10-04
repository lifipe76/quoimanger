<?php

namespace App\Entity;

use App\Entity\Traits\DatesTrait;
use App\Repository\RealisationNoteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RealisationNoteRepository::class)]
#[ORM\Table(name: 'realisation_notes')]
#[ORM\UniqueConstraint(name: 'UNIQ_REALISATION_USER', columns: ['realisation_id', 'user_id'])]
#[ORM\HasLifecycleCallbacks]
class RealisationNote
{
    use DatesTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RecetteRealisation::class, inversedBy: 'notes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?RecetteRealisation $realisation = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?User $user = null;

    #[ORM\Column(type: 'smallint')]
    #[Assert\NotNull]
    #[Assert\Range(min: 1, max: 5, notInRangeMessage: 'La note doit être comprise entre 1 et 5 étoiles.')]
    private int $note = 5;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRealisation(): ?RecetteRealisation
    {
        return $this->realisation;
    }

    public function setRealisation(?RecetteRealisation $realisation): static
    {
        $this->realisation = $realisation;

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

    public function getNote(): int
    {
        return $this->note;
    }

    public function setNote(int $note): static
    {
        $this->note = max(1, min(5, $note));

        return $this;
    }
}
