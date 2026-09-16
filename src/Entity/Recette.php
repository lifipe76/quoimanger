<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Entity\Traits\DatesTrait;
use App\Repository\RecetteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RecetteRepository::class)]
#[ORM\Table(name: 'recettes')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Post(),
        new Get(),
        new Put(),
        new Patch(),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['recette:read']],
    denormalizationContext: ['groups' => ['recette:write']]
)]
class Recette
{
    use DatesTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['recette:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'La désignation de la recette est obligatoire.')]
    #[Assert\Length(max: 255)]
    #[Groups(['recette:read', 'recette:write'])]
    private ?string $designation = null;

    /**
     * @var Collection<int, RecetteRealisation>
     */
    #[ORM\OneToMany(targetEntity: RecetteRealisation::class, mappedBy: 'recette', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['realiseAt' => 'DESC'])]
    #[Groups(['recette:read'])]
    private Collection $realisations;

    /**
     * @var Collection<int, RecetteIngredient>
     */
    #[ORM\OneToMany(targetEntity: RecetteIngredient::class, mappedBy: 'recette', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['recette:read', 'recette:write'])]
    #[Assert\Valid]
    private Collection $recetteIngredients;

    public function __construct()
    {
        $this->recetteIngredients = new ArrayCollection();
        $this->realisations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDesignation(): ?string
    {
        return $this->designation;
    }

    public function setDesignation(string $designation): static
    {
        $this->designation = $designation;

        return $this;
    }

    /**
     * @return Collection<int, RecetteRealisation>
     */
    public function getRealisations(): Collection
    {
        return $this->realisations;
    }

    public function addRealisation(RecetteRealisation $realisation): static
    {
        if (!$this->realisations->contains($realisation)) {
            $this->realisations->add($realisation);
            $realisation->setRecette($this);
        }

        return $this;
    }

    public function removeRealisation(RecetteRealisation $realisation): static
    {
        if ($this->realisations->removeElement($realisation)) {
            if ($realisation->getRecette() === $this) {
                $realisation->setRecette(null);
            }
        }

        return $this;
    }

    #[Groups(['recette:read'])]
    public function getLastRealiseAt(): ?\DateTimeImmutable
    {
        if ($this->realisations->isEmpty()) {
            return null;
        }

        return $this->realisations->first()->getRealiseAt();
    }

    public function getRealiseAt(): ?\DateTimeImmutable
    {
        return $this->getLastRealiseAt();
    }

    /**
     * @return Collection<int, RecetteIngredient>
     */
    public function getRecetteIngredients(): Collection
    {
        return $this->recetteIngredients;
    }

    public function addRecetteIngredient(RecetteIngredient $recetteIngredient): static
    {
        if (!$this->recetteIngredients->contains($recetteIngredient)) {
            $this->recetteIngredients->add($recetteIngredient);
            $recetteIngredient->setRecette($this);
        }

        return $this;
    }

    public function removeRecetteIngredient(RecetteIngredient $recetteIngredient): static
    {
        if ($this->recetteIngredients->removeElement($recetteIngredient)) {
            if ($recetteIngredient->getRecette() === $this) {
                $recetteIngredient->setRecette(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->designation;
    }
}
