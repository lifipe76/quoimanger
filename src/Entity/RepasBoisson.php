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
use App\Repository\RepasBoissonRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RepasBoissonRepository::class)]
#[ORM\Table(name: 'repas_boissons')]
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
    normalizationContext: ['groups' => ['repas_boisson:read']],
    denormalizationContext: ['groups' => ['repas_boisson:write']]
)]
class RepasBoisson
{
    use DatesTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['repas_boisson:read', 'realisation:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RecetteRealisation::class, inversedBy: 'repasBoissons')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Le repas est obligatoire.')]
    #[Groups(['repas_boisson:read', 'repas_boisson:write'])]
    private ?RecetteRealisation $realisation = null;

    #[ORM\ManyToOne(targetEntity: Boisson::class, inversedBy: 'repasBoissons')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'La boisson est obligatoire.')]
    #[Groups(['repas_boisson:read', 'repas_boisson:write', 'realisation:read'])]
    private ?Boisson $boisson = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Groups(['repas_boisson:read', 'repas_boisson:write', 'realisation:read'])]
    private ?\DateTimeImmutable $dateAchat = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Assert\Range(min: 1, max: 5, notInRangeMessage: 'La note doit être comprise entre {{ min }} et {{ max }}.')]
    #[Groups(['repas_boisson:read', 'repas_boisson:write', 'realisation:read'])]
    private ?int $note = null;

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

    public function getBoisson(): ?Boisson
    {
        return $this->boisson;
    }

    public function setBoisson(?Boisson $boisson): static
    {
        $this->boisson = $boisson;

        return $this;
    }

    public function getDateAchat(): ?\DateTimeImmutable
    {
        return $this->dateAchat;
    }

    public function setDateAchat(?\DateTimeImmutable $dateAchat): static
    {
        $this->dateAchat = $dateAchat;

        return $this;
    }

    public function getNote(): ?int
    {
        return $this->note;
    }

    public function setNote(?int $note): static
    {
        $this->note = $note;

        return $this;
    }
}
