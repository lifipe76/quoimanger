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
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
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

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['realisation:read', 'realisation:write', 'recette:read'])]
    private ?string $photo = null;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['realisation:read', 'realisation:write', 'recette:read'])]
    private array $photos = [];

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'realisation_participants')]
    #[Groups(['realisation:read', 'realisation:write'])]
    private Collection $participants;

    /**
     * @var Collection<int, RealisationNote>
     */
    #[ORM\OneToMany(targetEntity: RealisationNote::class, mappedBy: 'realisation', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['realisation:read'])]
    private Collection $notes;

    /**
     * @var Collection<int, RepasBoisson>
     */
    #[ORM\OneToMany(targetEntity: RepasBoisson::class, mappedBy: 'realisation', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['realisation:read'])]
    private Collection $repasBoissons;

    public function __construct()
    {
        $this->realiseAt = new \DateTimeImmutable();
        $this->participants = new ArrayCollection();
        $this->notes = new ArrayCollection();
        $this->repasBoissons = new ArrayCollection();
        $this->photos = [];
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
     * @return list<string>
     */
    public function getPhotos(): array
    {
        if (!isset($this->photos) || !is_array($this->photos)) {
            $this->photos = [];
        }

        if (empty($this->photos) && $this->photo !== null && $this->photo !== '') {
            return [$this->photo];
        }

        return $this->photos;
    }

    /**
     * @param list<string> $photos
     */
    public function setPhotos(array $photos): static
    {
        $clean = array_values(array_filter(array_map('trim', $photos)));
        $this->photos = $clean;
        $this->photo = $clean[0] ?? null;

        return $this;
    }

    public function addPhoto(string $photo): static
    {
        if (!isset($this->photos) || !is_array($this->photos)) {
            $this->photos = [];
        }
        $photo = trim($photo);
        if ($photo !== '' && !in_array($photo, $this->photos, true)) {
            $this->photos[] = $photo;
            if ($this->photo === null) {
                $this->photo = $photo;
            }
        }

        return $this;
    }

    public function removePhoto(string $photo): static
    {
        if (!isset($this->photos) || !is_array($this->photos)) {
            $this->photos = [];
        }
        $this->photos = array_values(array_diff($this->photos, [$photo]));
        $this->photo = $this->photos[0] ?? null;

        return $this;
    }

    public function getPhoto(): ?string
    {
        return $this->photo ?? ($this->photos[0] ?? null);
    }

    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo !== null ? trim($photo) : null;
        if ($this->photo === '') {
            $this->photo = null;
        }
        $this->photos = $this->photo !== null ? [$this->photo] : [];

        return $this;
    }

    public function getPhotoUrl(): ?string
    {
        $photo = $this->getPhoto();
        if ($photo === null || $photo === '') {
            return null;
        }

        if (str_starts_with($photo, 'http://') || str_starts_with($photo, 'https://')) {
            return $photo;
        }

        return '/photos/repas/' . rawurlencode($photo);
    }

    /**
     * @return list<string>
     */
    #[Groups(['realisation:read', 'recette:read'])]
    public function getPhotoUrls(): array
    {
        $urls = [];
        foreach ($this->getPhotos() as $p) {
            if ($p === '') {
                continue;
            }
            if (str_starts_with($p, 'http://') || str_starts_with($p, 'https://')) {
                $urls[] = $p;
            } else {
                $urls[] = '/photos/repas/' . rawurlencode($p);
            }
        }

        return $urls;
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

    /**
     * @return Collection<int, User>
     */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    public function addParticipant(User $user): static
    {
        if (!$this->participants->contains($user)) {
            $this->participants->add($user);
        }

        return $this;
    }

    public function removeParticipant(User $user): static
    {
        $this->participants->removeElement($user);

        return $this;
    }

    public function clearParticipants(): static
    {
        $this->participants->clear();

        return $this;
    }

    public function isPourTous(): bool
    {
        return $this->participants->isEmpty();
    }

    public function hasParticipant(User $user): bool
    {
        return $this->isPourTous() || $this->participants->contains($user);
    }

    /**
     * @return Collection<int, RealisationNote>
     */
    public function getNotes(): Collection
    {
        return $this->notes;
    }

    public function addNote(RealisationNote $note): static
    {
        if (!$this->notes->contains($note)) {
            $this->notes->add($note);
            $note->setRealisation($this);
        }

        return $this;
    }

    public function removeNote(RealisationNote $note): static
    {
        if ($this->notes->removeElement($note)) {
            if ($note->getRealisation() === $this) {
                $note->setRealisation(null);
            }
        }

        return $this;
    }

    public function getNoteForUser(?User $user): ?int
    {
        if (!$user) {
            return null;
        }

        foreach ($this->notes as $note) {
            if ($note->getUser() && $note->getUser()->getId() === $user->getId()) {
                return $note->getNote();
            }
        }

        return null;
    }

    public function getNotesCount(): int
    {
        return $this->notes->count();
    }

    public function getAverageNote(): ?float
    {
        if ($this->notes->isEmpty()) {
            return null;
        }

        $total = 0;
        foreach ($this->notes as $note) {
            $total += $note->getNote();
        }

        return round($total / $this->notes->count(), 1);
    }

    /**
     * @return Collection<int, RepasBoisson>
     */
    public function getRepasBoissons(): Collection
    {
        return $this->repasBoissons;
    }

    public function addRepasBoisson(RepasBoisson $repasBoisson): static
    {
        if (!$this->repasBoissons->contains($repasBoisson)) {
            $this->repasBoissons->add($repasBoisson);
            $repasBoisson->setRealisation($this);
        }

        return $this;
    }

    public function removeRepasBoisson(RepasBoisson $repasBoisson): static
    {
        if ($this->repasBoissons->removeElement($repasBoisson)) {
            if ($repasBoisson->getRealisation() === $this) {
                $repasBoisson->setRealisation(null);
            }
        }

        return $this;
    }

    /**
     * Retourne la première boisson associée au repas ou null.
     */
    public function getBoisson(): ?Boisson
    {
        $first = $this->repasBoissons->first();

        return $first ? $first->getBoisson() : null;
    }

    /**
     * Retourne la première relation RepasBoisson ou null.
     */
    public function getRepasBoisson(): ?RepasBoisson
    {
        return $this->repasBoissons->first() ?: null;
    }
}
