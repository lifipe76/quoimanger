<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\RecetteIngredientRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RecetteIngredientRepository::class)]
#[ORM\Table(name: 'recette_ingredients')]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Post(),
        new Get(),
        new Put(),
        new Patch(),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['recette_ingredient:read']],
    denormalizationContext: ['groups' => ['recette_ingredient:write']]
)]
class RecetteIngredient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['recette_ingredient:read', 'recette:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Recette::class, inversedBy: 'recetteIngredients')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['recette_ingredient:read', 'recette_ingredient:write'])]
    private ?Recette $recette = null;

    #[ORM\ManyToOne(targetEntity: Ingredient::class, inversedBy: 'recetteIngredients')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'L\'ingrédient est obligatoire.')]
    #[Groups(['recette_ingredient:read', 'recette_ingredient:write', 'recette:read', 'recette:write'])]
    private ?Ingredient $ingredient = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['recette_ingredient:read', 'recette_ingredient:write', 'recette:read', 'recette:write'])]
    private ?string $quantite = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRecette(): ?Recette
    {
        return $this->recette;
    }

    public function setRecette(?Recette $recette): static
    {
        $this->recette = $recette;

        return $this;
    }

    public function getIngredient(): ?Ingredient
    {
        return $this->ingredient;
    }

    public function setIngredient(?Ingredient $ingredient): static
    {
        $this->ingredient = $ingredient;

        return $this;
    }

    public function getQuantite(): ?string
    {
        return $this->quantite;
    }

    public function setQuantite(?string $quantite): static
    {
        $this->quantite = $quantite;

        return $this;
    }
}
