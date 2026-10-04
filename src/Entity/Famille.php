<?php

namespace App\Entity;

use App\Entity\Traits\DatesTrait;
use App\Repository\FamilleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FamilleRepository::class)]
#[ORM\Table(name: 'familles')]
#[ORM\HasLifecycleCallbacks]
class Famille
{
    use DatesTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $nom = 'Ma famille';

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(mappedBy: 'famille', targetEntity: User::class)]
    private Collection $membres;

    /**
     * @var Collection<int, FamilleInvitation>
     */
    #[ORM\OneToMany(mappedBy: 'famille', targetEntity: FamilleInvitation::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $invitations;

    public function __construct()
    {
        $this->membres = new ArrayCollection();
        $this->invitations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = trim($nom);
        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getMembres(): Collection
    {
        return $this->membres;
    }

    public function addMembre(User $membre): static
    {
        if (!$this->membres->contains($membre)) {
            $this->membres->add($membre);
            $membre->setFamille($this);
        }

        return $this;
    }

    public function removeMembre(User $membre): static
    {
        if ($this->membres->removeElement($membre)) {
            if ($membre->getFamille() === $this) {
                $membre->setFamille(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, FamilleInvitation>
     */
    public function getInvitations(): Collection
    {
        return $this->invitations;
    }

    public function addInvitation(FamilleInvitation $invitation): static
    {
        if (!$this->invitations->contains($invitation)) {
            $this->invitations->add($invitation);
            $invitation->setFamille($this);
        }

        return $this;
    }

    public function removeInvitation(FamilleInvitation $invitation): static
    {
        $this->invitations->removeElement($invitation);
        return $this;
    }
}
