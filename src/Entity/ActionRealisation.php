<?php

namespace App\Entity;

use App\Entity\Traits\DatesTrait;
use App\Repository\ActionRealisationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ActionRealisationRepository::class)]
#[ORM\Table(name: 'action_realisations')]
#[ORM\HasLifecycleCallbacks]
class ActionRealisation
{
    use DatesTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Action::class, inversedBy: 'realisations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'L\'action est obligatoire.')]
    private ?Action $action = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'La date de réalisation est obligatoire.')]
    private ?\DateTimeImmutable $realiseAt = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $moment = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $complement = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $photos = [];

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'action_realisation_participants')]
    private Collection $participants;

    public function __construct()
    {
        $this->participants = new ArrayCollection();
        $this->photos = [];
        $this->realiseAt = new \DateTimeImmutable('today');
        $this->moment = 'matin';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAction(): ?Action
    {
        return $this->action;
    }

    public function setAction(?Action $action): static
    {
        $this->action = $action;

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
        return $this->moment;
    }

    public function setMoment(?string $moment): static
    {
        $this->moment = $moment;

        return $this;
    }

    public function getComplement(): ?string
    {
        return $this->complement;
    }

    public function setComplement(?string $complement): static
    {
        $this->complement = $complement;

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
     * @return Collection<int, User>
     */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    public function addParticipant(User $participant): static
    {
        if (!$this->participants->contains($participant)) {
            $this->participants->add($participant);
        }

        return $this;
    }

    public function removeParticipant(User $participant): static
    {
        $this->participants->removeElement($participant);

        return $this;
    }

    public function hasParticipant(User $user): bool
    {
        if ($this->participants->isEmpty()) {
            return true;
        }

        return $this->participants->contains($user);
    }

    public function getTempsDepuis(?\DateTimeInterface $now = null): ?string
    {
        if ($this->realiseAt === null) {
            return null;
        }

        $today = ($now instanceof \DateTimeImmutable ? $now : new \DateTimeImmutable('today'))->setTime(0, 0, 0);
        $realiseDay = $this->realiseAt->setTime(0, 0, 0);
        $diffDays = (int) $realiseDay->diff($today)->format('%r%a');

        if ($diffDays <= 0) {
            return 'Fait aujourd\'hui';
        }
        if ($diffDays === 1) {
            return 'Fait hier';
        }

        return 'Fait depuis ' . Action::formaterDureeJours($diffDays);
    }

    public function getProchaineEcheance(): ?\DateTimeImmutable
    {
        $action = $this->action;
        if (!$action || !$action->isPeriodique() || !$action->getFrequenceValeur() || !$action->getFrequenceUnite()) {
            return null;
        }

        if ($this->realiseAt === null) {
            return null;
        }

        $val = (int) $action->getFrequenceValeur();
        $intervalSpec = match ($action->getFrequenceUnite()) {
            'jour' => sprintf('+%d days', $val),
            'semaine' => sprintf('+%d weeks', $val),
            'mois' => sprintf('+%d months', $val),
            'annee' => sprintf('+%d years', $val),
            default => null,
        };

        if (!$intervalSpec) {
            return null;
        }

        return $this->realiseAt->modify($intervalSpec);
    }

    /**
     * @return array{status: string, isOverdue: bool, label: string, date: ?\DateTimeImmutable, diffDays: int}|null
     */
    public function getEcheanceInfo(?\DateTimeInterface $now = null): ?array
    {
        $prochaine = $this->getProchaineEcheance();
        if ($prochaine === null) {
            return null;
        }

        $today = ($now instanceof \DateTimeImmutable ? $now : new \DateTimeImmutable('today'))->setTime(0, 0, 0);
        $prochaineDay = $prochaine->setTime(0, 0, 0);
        $diffDays = (int) $today->diff($prochaineDay)->format('%r%a');

        $isOverdue = $diffDays < 0;

        return [
            'status' => $isOverdue ? 'en_retard' : ($diffDays === 0 ? 'aujourdhui' : 'a_venir'),
            'isOverdue' => $isOverdue,
            'label' => 'À refaire dans le ' . $prochaine->format('d/m/Y'),
            'date' => $prochaine,
            'diffDays' => $diffDays,
        ];
    }
}
