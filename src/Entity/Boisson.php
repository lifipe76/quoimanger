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
use App\Repository\BoissonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BoissonRepository::class)]
#[ORM\Table(name: 'boissons')]
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
    normalizationContext: ['groups' => ['boisson:read']],
    denormalizationContext: ['groups' => ['boisson:write']]
)]
class Boisson
{
    use DatesTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['boisson:read', 'realisation:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom de la boisson est obligatoire.')]
    #[Assert\Length(max: 255)]
    #[Groups(['boisson:read', 'boisson:write', 'realisation:read'])]
    private ?string $nom = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['boisson:read', 'boisson:write'])]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['boisson:read', 'boisson:write', 'realisation:read'])]
    private ?string $lieuAchat = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['boisson:read', 'boisson:write', 'realisation:read'])]
    private ?string $photo = null;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['boisson:read', 'boisson:write', 'realisation:read'])]
    private array $photos = [];

    /**
     * @var Collection<int, RepasBoisson>
     */
    #[ORM\OneToMany(targetEntity: RepasBoisson::class, mappedBy: 'boisson', cascade: ['remove'])]
    private Collection $repasBoissons;

    public function __construct()
    {
        $this->repasBoissons = new ArrayCollection();
        $this->photos = [];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    /**
     * Alias de compatibilité pour getNom.
     */
    public function getDesignation(): ?string
    {
        return $this->nom;
    }

    /**
     * Alias de compatibilité pour setNom.
     */
    public function setDesignation(string $designation): static
    {
        $this->nom = $designation;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getLieuAchat(): ?string
    {
        return $this->lieuAchat;
    }

    public function setLieuAchat(?string $lieuAchat): static
    {
        $this->lieuAchat = $lieuAchat;

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

    #[Groups(['boisson:read', 'realisation:read'])]
    public function getPhotoUrl(): ?string
    {
        $photo = $this->getPhoto();
        if ($photo === null || $photo === '') {
            return null;
        }

        if (str_starts_with($photo, 'http://') || str_starts_with($photo, 'https://')) {
            return $photo;
        }

        return '/photos/boissons/' . rawurlencode($photo);
    }

    /**
     * @return list<string>
     */
    #[Groups(['boisson:read', 'realisation:read'])]
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
                $urls[] = '/photos/boissons/' . rawurlencode($p);
            }
        }

        return $urls;
    }

    /**
     * @return Collection<int, RepasBoisson>
     */
    public function getRepasBoissons(): Collection
    {
        return $this->repasBoissons;
    }

    /**
     * Note moyenne attribuée à cette boisson lors des repas.
     */
    #[Groups(['boisson:read'])]
    public function getAverageNote(): ?float
    {
        $notes = [];
        foreach ($this->repasBoissons as $rb) {
            if ($rb->getNote() !== null && $rb->getNote() > 0) {
                $notes[] = $rb->getNote();
            }
        }

        if (empty($notes)) {
            return null;
        }

        return round(array_sum($notes) / count($notes), 1);
    }

    /**
     * Nombre d'évaluations de cette boisson.
     */
    #[Groups(['boisson:read'])]
    public function getNotesCount(): int
    {
        $count = 0;
        foreach ($this->repasBoissons as $rb) {
            if ($rb->getNote() !== null && $rb->getNote() > 0) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Nombre de fois où cette boisson a été servie / associée à un repas.
     */
    #[Groups(['boisson:read'])]
    public function getRealisationsCount(): int
    {
        return $this->repasBoissons->count();
    }
}
