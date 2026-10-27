<?php

namespace App\Entity;

use App\Entity\Traits\DatesTrait;
use App\Repository\ActionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isPeriodique = false;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Positive(message: 'La fréquence doit être un nombre positif supérieur à zéro.')]
    private ?int $frequenceValeur = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Choice(choices: ['jour', 'semaine', 'mois', 'annee'], message: 'Veuillez choisir une unité valide.')]
    private ?string $frequenceUnite = null;

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

    public function isPeriodique(): bool
    {
        return $this->isPeriodique;
    }

    public function setIsPeriodique(bool $isPeriodique): static
    {
        $this->isPeriodique = $isPeriodique;
        if (!$isPeriodique) {
            $this->frequenceValeur = null;
            $this->frequenceUnite = null;
        }

        return $this;
    }

    public function getFrequenceValeur(): ?int
    {
        return $this->frequenceValeur;
    }

    public function setFrequenceValeur(?int $frequenceValeur): static
    {
        $this->frequenceValeur = $frequenceValeur;

        return $this;
    }

    public function getFrequenceUnite(): ?string
    {
        return $this->frequenceUnite;
    }

    public function setFrequenceUnite(?string $frequenceUnite): static
    {
        $this->frequenceUnite = $frequenceUnite;

        return $this;
    }

    public function getFrequenceTexte(): ?string
    {
        if (!$this->isPeriodique || !$this->frequenceValeur || !$this->frequenceUnite) {
            return null;
        }

        $val = (int) $this->frequenceValeur;
        return match ($this->frequenceUnite) {
            'jour' => $val === 1 ? 'Tous les jours' : sprintf('Tous les %d jours', $val),
            'semaine' => $val === 1 ? 'Toutes les semaines' : sprintf('Toutes les %d semaines', $val),
            'mois' => $val === 1 ? 'Tous les mois' : sprintf('Tous les %d mois', $val),
            'annee' => $val === 1 ? 'Tous les ans' : sprintf('Tous les %d ans', $val),
            default => sprintf('Tous les %d %s', $val, $this->frequenceUnite),
        };
    }

    public function getProchaineEcheance(): ?\DateTimeImmutable
    {
        if (!$this->isPeriodique || !$this->frequenceValeur || !$this->frequenceUnite) {
            return null;
        }

        $baseDate = $this->getLastRealiseAt();
        if ($baseDate === null) {
            return null;
        }

        $val = (int) $this->frequenceValeur;
        $intervalSpec = match ($this->frequenceUnite) {
            'jour' => sprintf('+%d days', $val),
            'semaine' => sprintf('+%d weeks', $val),
            'mois' => sprintf('+%d months', $val),
            'annee' => sprintf('+%d years', $val),
            default => null,
        };

        if (!$intervalSpec) {
            return null;
        }

        return $baseDate->modify($intervalSpec);
    }

    /**
     * @return array{status: string, isOverdue: bool, label: string, date: ?\DateTimeImmutable, diffDays: int}|null
     */
    public function getEcheanceInfo(?\DateTimeInterface $now = null): ?array
    {
        if (!$this->isPeriodique || !$this->frequenceValeur || !$this->frequenceUnite) {
            return null;
        }

        $today = ($now instanceof \DateTimeImmutable ? $now : new \DateTimeImmutable('today'))->setTime(0, 0, 0);
        $lastRealise = $this->getLastRealiseAt();

        if ($lastRealise === null) {
            return [
                'status' => 'a_faire',
                'isOverdue' => true,
                'label' => 'À faire',
                'date' => null,
                'diffDays' => 0,
            ];
        }

        $prochaine = $this->getProchaineEcheance();
        if ($prochaine === null) {
            return null;
        }

        $prochaineDay = $prochaine->setTime(0, 0, 0);
        // %r gives "-" if negative, "" if positive
        $diffDays = (int) $today->diff($prochaineDay)->format('%r%a');

        if ($diffDays === 0) {
            return [
                'status' => 'aujourdhui',
                'isOverdue' => true,
                'label' => 'À refaire aujourd\'hui',
                'date' => $prochaine,
                'diffDays' => 0,
            ];
        }

        if ($diffDays < 0) {
            $retard = abs($diffDays);
            return [
                'status' => 'en_retard',
                'isOverdue' => true,
                'label' => 'En retard de ' . self::formaterDureeJours($retard),
                'date' => $prochaine,
                'diffDays' => $diffDays,
            ];
        }

        return [
            'status' => 'a_venir',
            'isOverdue' => false,
            'label' => 'À refaire dans ' . self::formaterDureeJours($diffDays),
            'date' => $prochaine,
            'diffDays' => $diffDays,
        ];
    }

    public function getTempsDepuisDerniereRealisation(?\DateTimeInterface $now = null): ?string
    {
        $lastRealise = $this->getLastRealiseAt();
        if ($lastRealise === null) {
            return null;
        }

        $today = ($now instanceof \DateTimeImmutable ? $now : new \DateTimeImmutable('today'))->setTime(0, 0, 0);
        $realiseDay = $lastRealise->setTime(0, 0, 0);
        $diffDays = (int) $realiseDay->diff($today)->format('%r%a');

        if ($diffDays <= 0) {
            return 'Fait aujourd\'hui';
        }
        if ($diffDays === 1) {
            return 'Fait hier';
        }

        return 'Fait depuis ' . self::formaterDureeJours($diffDays);
    }

    #[Assert\Callback]
    public function validatePeriodicite(ExecutionContextInterface $context): void
    {
        if ($this->isPeriodique) {
            if (!$this->frequenceValeur || $this->frequenceValeur < 1) {
                $context->buildViolation('Veuillez préciser la fréquence (ex: 1, 2, 3...).')
                    ->atPath('frequenceValeur')
                    ->addViolation();
            }
            if (!$this->frequenceUnite || !in_array($this->frequenceUnite, ['jour', 'semaine', 'mois', 'annee'], true)) {
                $context->buildViolation('Veuillez choisir une unité de période (jours, semaines, mois ou années).')
                    ->atPath('frequenceUnite')
                    ->addViolation();
            }
        }
    }

    public static function formaterDureeJours(int $jours): string
    {
        if ($jours < 7) {
            return $jours . ' jour' . ($jours > 1 ? 's' : '');
        }
        if ($jours < 30) {
            $semaines = (int) round($jours / 7);
            if ($semaines <= 1) {
                return '1 semaine';
            }
            return $semaines . ' semaines';
        }
        if ($jours < 365) {
            $mois = (int) round($jours / 30.4375);
            if ($mois <= 1) {
                return '1 mois';
            }
            return $mois . ' mois';
        }

        $annees = (int) round($jours / 365.25);
        if ($annees <= 1) {
            return '1 an';
        }
        return $annees . ' ans';
    }
}
