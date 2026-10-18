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
     * @var Collection<int, Recette>
     */
    #[ORM\ManyToMany(targetEntity: Recette::class, inversedBy: 'multiRealisations')]
    #[ORM\JoinTable(name: 'realisation_recettes')]
    #[Groups(['realisation:read', 'realisation:write'])]
    private Collection $recettes;

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

    public function __construct()
    {
        $this->realiseAt = new \DateTimeImmutable();
        $this->participants = new ArrayCollection();
        $this->notes = new ArrayCollection();
        $this->photos = [];
        $this->recettes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRecette(): ?Recette
    {
        if ($this->recette === null && !$this->recettes->isEmpty()) {
            return $this->recettes->first();
        }

        return $this->recette;
    }

    public function setRecette(?Recette $recette): static
    {
        $this->recette = $recette;
        if ($recette !== null && !$this->recettes->contains($recette)) {
            $this->recettes->add($recette);
        }

        return $this;
    }

    /**
     * @return Collection<int, Recette>
     */
    public function getRecettes(): Collection
    {
        if ($this->recettes->isEmpty() && $this->recette !== null) {
            $this->recettes->add($this->recette);
        }

        return $this->recettes;
    }

    public function addRecette(Recette $recette): static
    {
        if (!$this->recettes->contains($recette)) {
            $this->recettes->add($recette);
            if ($this->recette === null) {
                $this->recette = $recette;
            }
        }

        return $this;
    }

    public function removeRecette(Recette $recette): static
    {
        if ($this->recettes->removeElement($recette)) {
            if ($this->recette === $recette) {
                $this->recette = $this->recettes->first() ?: null;
            }
        }

        return $this;
    }

    /**
     * @param iterable<Recette> $recettes
     */
    public function setRecettes(iterable $recettes): static
    {
        $this->recettes->clear();
        foreach ($recettes as $r) {
            $this->addRecette($r);
        }
        if (!$this->recettes->isEmpty()) {
            if ($this->recette === null || !$this->recettes->contains($this->recette)) {
                $this->recette = $this->recettes->first();
            }
        } else {
            $this->recette = null;
        }

        return $this;
    }

    /**
     * @return list<array{id: int, designation: string}>
     */
    #[Groups(['realisation:read'])]
    public function getRecettesData(): array
    {
        $data = [];
        foreach ($this->getRecettes() as $r) {
            if ($r && $r->getId()) {
                $data[] = [
                    'id' => $r->getId(),
                    'designation' => (string) $r->getDesignation(),
                ];
            }
        }

        return $data;
    }

    /**
     * Fusionne intelligemment les titres de recettes d'un repas :
     * - Ex: "Steak" + "Frites" -> "Steak, frites"
     * - Ex: "Pizza" + "Pizza jambon" -> "Pizza jambon" (texte commun mis 1 fois)
     *
     * @param list<string> $titles
     */
    public static function mergeRecipeTitles(array $titles): string
    {
        $clean = [];
        foreach ($titles as $t) {
            $t = trim((string) $t);
            if ($t === '') {
                continue;
            }
            $lower = mb_strtolower($t, 'UTF-8');
            $found = false;
            foreach ($clean as $existing) {
                if (mb_strtolower($existing, 'UTF-8') === $lower) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $clean[] = $t;
            }
        }

        if (empty($clean)) {
            return '';
        }
        if (count($clean) === 1) {
            return $clean[0];
        }

        // Élimination des titres subsumés (ex: "pizza" dans "pizza jambon")
        $filtered = [];
        foreach ($clean as $i => $titleA) {
            $subsumed = false;
            $normA = mb_strtolower($titleA, 'UTF-8');
            foreach ($clean as $j => $titleB) {
                if ($i === $j) {
                    continue;
                }
                $normB = mb_strtolower($titleB, 'UTF-8');
                if (mb_strlen($normB, 'UTF-8') > mb_strlen($normA, 'UTF-8') && str_contains($normB, $normA)) {
                    $subsumed = true;
                    break;
                }
            }
            if (!$subsumed) {
                $filtered[] = $titleA;
            }
        }

        if (empty($filtered)) {
            $filtered = [$clean[0]];
        }

        if (count($filtered) === 1) {
            return $filtered[0];
        }

        $result = [];
        foreach ($filtered as $idx => $t) {
            if ($idx === 0) {
                $firstChar = mb_strtoupper(mb_substr($t, 0, 1, 'UTF-8'), 'UTF-8');
                $rest = mb_substr($t, 1, null, 'UTF-8');
                $result[] = $firstChar . $rest;
            } else {
                $result[] = mb_strtolower($t, 'UTF-8');
            }
        }

        return implode(', ', $result);
    }

    #[Groups(['realisation:read'])]
    public function getDisplayTitle(): string
    {
        $recettes = $this->getRecettes();
        if ($recettes->isEmpty()) {
            return $this->recette ? (string) $this->recette->getDesignation() : 'Repas';
        }

        $titles = [];
        foreach ($recettes as $r) {
            $d = trim((string) $r->getDesignation());
            if ($d !== '') {
                $titles[] = $d;
            }
        }

        if (empty($titles)) {
            return $this->recette ? (string) $this->recette->getDesignation() : 'Repas';
        }

        return self::mergeRecipeTitles($titles);
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
}
