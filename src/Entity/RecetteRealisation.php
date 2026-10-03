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

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['realisation:read', 'realisation:write', 'recette:read'])]
    private ?string $moment = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['realisation:read', 'realisation:write', 'recette:read'])]
    private ?string $complement = null;

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

    public function getMoment(): ?string
    {
        if ($this->moment !== null && $this->moment !== '') {
            return $this->moment;
        }

        if ($this->realiseAt) {
            $hour = (int) $this->realiseAt->format('G');
            if ($hour < 11) {
                return 'matin';
            }
            if ($hour < 16) {
                return 'midi';
            }
            return 'soir';
        }

        return null;
    }

    public function setMoment(?string $moment): static
    {
        $this->moment = $moment ? strtolower(trim($moment)) : null;

        return $this;
    }

    public function getMomentLabel(): string
    {
        return match ($this->getMoment()) {
            'matin' => 'Matin',
            'midi' => 'Midi',
            'soir' => 'Soir',
            default => '',
        };
    }

    public function getComplement(): ?string
    {
        return $this->complement;
    }

    public function setComplement(?string $complement): static
    {
        $this->complement = $complement !== null ? trim($complement) : null;
        if ($this->complement === '') {
            $this->complement = null;
        }

        return $this;
    }

    /**
     * Retourne la réalisation de la même recette immédiatement antérieure à celle-ci
     */
    public function getPreviousRealisation(): ?self
    {
        if (!$this->recette || !$this->realiseAt) {
            return null;
        }

        $previous = null;
        foreach ($this->recette->getRealisations() as $other) {
            if ($other === $this || ($this->id !== null && $other->getId() === $this->id)) {
                continue;
            }
            if ($other->getRealiseAt() && $other->getRealiseAt() < $this->realiseAt) {
                if ($previous === null || $other->getRealiseAt() > $previous->getRealiseAt()) {
                    $previous = $other;
                }
            }
        }

        return $previous;
    }

    /**
     * Retourne le nombre de jours écoulés depuis la précédente réalisation de cette recette, ou null si 1ère fois
     */
    public function getDaysSincePreviousRealisation(): ?int
    {
        $previous = $this->getPreviousRealisation();
        if (!$previous || !$previous->getRealiseAt() || !$this->realiseAt) {
            return null;
        }

        return (int) $this->realiseAt->diff($previous->getRealiseAt())->days;
    }

    /**
     * Retourne le libellé pour la bulle d'intervalle sur la card de timeline
     */
    public function getIntervalLabel(): string
    {
        $days = $this->getDaysSincePreviousRealisation();
        if ($days === null) {
            return '1ère fois';
        }

        if ($days === 0) {
            return 'Même jour';
        }

        if ($days === 1) {
            return 'Ça faisait 1 jour';
        }

        return sprintf('Ça faisait %d jours', $days);
    }
}
