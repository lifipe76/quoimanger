<?php

namespace App\Entity;

use App\Entity\Traits\DatesTrait;
use App\Repository\ActionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ActionRepository::class)]
#[ORM\Table(name: 'actions')]
#[ORM\HasLifecycleCallbacks]
class Action
{
    use DatesTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'La désignation de l\'action est obligatoire.')]
    #[Assert\Length(max: 255)]
    private ?string $designation = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $photos = [];

    /**
     * @var Collection<int, ActionRealisation>
     */
    #[ORM\OneToMany(targetEntity: ActionRealisation::class, mappedBy: 'action', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['realiseAt' => 'DESC'])]
    private Collection $realisations;

    public function __construct()
    {
        $this->realisations = new ArrayCollection();
        $this->photos = [];
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

        return '/photos/actions/' . rawurlencode($photo);
    }

    /**
     * @return list<string>
     */
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
                $urls[] = '/photos/actions/' . rawurlencode($p);
            }
        }

        return $urls;
    }

    /**
     * @return Collection<int, ActionRealisation>
     */
    public function getRealisations(): Collection
    {
        return $this->realisations;
    }

    public function addRealisation(ActionRealisation $realisation): static
    {
        if (!$this->realisations->contains($realisation)) {
            $this->realisations->add($realisation);
            $realisation->setAction($this);
        }

        return $this;
    }

    public function removeRealisation(ActionRealisation $realisation): static
    {
        if ($this->realisations->removeElement($realisation)) {
            if ($realisation->getAction() === $this) {
                $realisation->setAction(null);
            }
        }

        return $this;
    }

    public function getLastRealiseAt(): ?\DateTimeImmutable
    {
        if ($this->realisations->isEmpty()) {
            return null;
        }

        $latest = null;
        foreach ($this->realisations as $realisation) {
            $date = $realisation->getRealiseAt();
            if ($date === null) {
                continue;
            }
            if ($latest === null || $date > $latest) {
                $latest = $date;
            }
        }

        return $latest;
    }

    public function getRealisationsCount(): int
    {
        return $this->realisations->count();
    }
}
