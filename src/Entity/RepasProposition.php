<?php

namespace App\Entity;

use App\Entity\Traits\DatesTrait;
use App\Repository\RepasPropositionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RepasPropositionRepository::class)]
#[ORM\Table(name: 'repas_propositions')]
#[ORM\HasLifecycleCallbacks]
class RepasProposition
{
    use DatesTrait;

    public const STATUS_EN_ATTENTE = 'en_attente';
    public const STATUS_VALIDEE = 'validee';
    public const STATUS_REJETEE = 'rejetee';
    public const STATUS_AJOUTEE_AU_FIL = 'ajoutee_au_fil';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Famille::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Famille $famille = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $proposePar = null;

    #[ORM\ManyToOne(targetEntity: Recette::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Recette $recette = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dateRepas = null;

    #[ORM\Column(length: 20)]
    private string $moment = 'soir';

    #[ORM\Column(length: 30)]
    private string $status = self::STATUS_EN_ATTENTE;

    #[ORM\ManyToOne(targetEntity: RecetteRealisation::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?RecetteRealisation $realisation = null;

    /**
     * @var Collection<int, RepasVote>
     */
    #[ORM\OneToMany(mappedBy: 'proposition', targetEntity: RepasVote::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $votes;

    public function __construct()
    {
        $this->votes = new ArrayCollection();
        $this->status = self::STATUS_EN_ATTENTE;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFamille(): ?Famille
    {
        return $this->famille;
    }

    public function setFamille(?Famille $famille): static
    {
        $this->famille = $famille;
        return $this;
    }

    public function getProposePar(): ?User
    {
        return $this->proposePar;
    }

    public function setProposePar(?User $proposePar): static
    {
        $this->proposePar = $proposePar;
        return $this;
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

    public function getDateRepas(): ?\DateTimeInterface
    {
        return $this->dateRepas;
    }

    public function setDateRepas(\DateTimeInterface $dateRepas): static
    {
        $this->dateRepas = $dateRepas;
        return $this;
    }

    public function getMoment(): string
    {
        return $this->moment;
    }

    public function setMoment(string $moment): static
    {
        $this->moment = strtolower(trim($moment));
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
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

    /**
     * @return Collection<int, RepasVote>
     */
    public function getVotes(): Collection
    {
        return $this->votes;
    }

    public function addVote(RepasVote $vote): static
    {
        if (!$this->votes->contains($vote)) {
            $this->votes->add($vote);
            $vote->setProposition($this);
        }
        return $this;
    }

    public function removeVote(RepasVote $vote): static
    {
        if ($this->votes->removeElement($vote)) {
            if ($vote->getProposition() === $this) {
                $vote->setProposition(null);
            }
        }
        return $this;
    }

    /**
     * @return array<RepasVote>
     */
    public function getVotesPour(): array
    {
        return array_values(array_filter(
            $this->votes->toArray(),
            fn (RepasVote $v) => $v->getChoix() === RepasVote::CHOIX_POUR
        ));
    }

    /**
     * @return array<RepasVote>
     */
    public function getVotesContre(): array
    {
        return array_values(array_filter(
            $this->votes->toArray(),
            fn (RepasVote $v) => $v->getChoix() === RepasVote::CHOIX_CONTRE
        ));
    }

    public function getUserVote(User $user): ?RepasVote
    {
        foreach ($this->votes as $vote) {
            if ($vote->getUser()?->getId() === $user->getId()) {
                return $vote;
            }
        }
        return null;
    }

    public function isValidee(): bool
    {
        return in_array($this->status, [self::STATUS_VALIDEE, self::STATUS_AJOUTEE_AU_FIL], true);
    }

    public function isAjouteeAuFil(): bool
    {
        return $this->status === self::STATUS_AJOUTEE_AU_FIL;
    }
}
