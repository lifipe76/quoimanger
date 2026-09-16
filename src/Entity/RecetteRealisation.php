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
use App\Repository\RecetteRealisationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RecetteRealisationRepository::class)]
#[ORM\Table(name: 'recette_realisations')]
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
    normalizationContext: ['groups' => ['realisation:read']],
    denormalizationContext: ['groups' => ['realisation:write']]
)]
class RecetteRealisation
{
    use DatesTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['realisation:read', 'recette:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Recette::class, inversedBy: 'realisations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'La recette est obligatoire.')]
    #[Groups(['realisation:read', 'realisation:write'])]
    private ?Recette $recette = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'La date de réalisation est obligatoire.')]
    #[Groups(['realisation:read', 'realisation:write', 'recette:read'])]
    private ?\DateTimeImmutable $realiseAt = null;

    public function __construct()
    {
        $this->realiseAt = new \DateTimeImmutable();
    }

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

    public function getRealiseAt(): ?\DateTimeImmutable
    {
        return $this->realiseAt;
    }

    public function setRealiseAt(\DateTimeImmutable $realiseAt): static
    {
        $this->realiseAt = $realiseAt;

        return $this;
    }
}
