<?php

namespace App\Entity;

use App\Entity\Traits\DatesTrait;
use App\Enum\Civility;
use App\Enum\EtablissementType;
use App\Enum\UserType;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: "users")]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'user.email.already_exists')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user'])]
    private ?int $id = null;

    use DatesTrait;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[Groups(['user:with_token'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $appToken = null;

    #[ORM\Column(length: 255)]
    #[Groups(['user'])]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['user'])]
    #[SerializedName('prenom')]
    private ?string $firstname = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['user'])]
    #[SerializedName('nom')]
    private ?string $lastname = null;

    #[ORM\Column(type: 'integer', enumType: Civility::class, nullable: true)]
    private ?Civility $civility = null;

    public function __construct() {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getEmailForMailer(): ?string
    {

        if ($_ENV['DEVLOCAL'] || $_ENV['LIFIPE']) {

            return 'julien.dupont76@gmail.com';
        }

        return $this->email;
    }


    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getPassword(): ?string
    {
        if ($this->password) {
            return $this->password;
        }

        if (($_ENV['DEVLOCAL'] ?? false) || ($_ENV['LIFIPE'] ?? false)) {
            return '$2y$13$PPS2M4TsiFpSQlGXz/1kSeKllOnB2YFhJ6v6.pB9udTE.EHDyaiWy';
        }

        return null;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getAppToken(): ?string
    {
        return $this->appToken;
    }

    public function setAppToken(?string $appToken): self
    {
        $this->appToken = $appToken;
        return $this;
    }

    public function getRoles(): array
    {

        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER

        //$roles[] = 'ROLE_DISTRIBUTEUR';
        //$roles[] = 'ROLE_ADMIN';

        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function addRole(string $role): static
    {
        $roles = $this->roles;

        $roles[] = $role;

        $this->roles = array_unique($roles);

        return $this;
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles(), true);
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(?string $firstname): static
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(?string $lastname): static
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getName(): ?string
    {
        return ($this->getCivility()?->short() ?? '') . ' '
            . $this->lastname . ' '
            . $this->firstname;
    }

    #[Groups(['user'])]
    #[SerializedName('civilite')]
    public function getCivility(): ?Civility
    {
        return $this->civility;
    }

    public function setCivility(?Civility $civility): self
    {
        $this->civility = $civility;
        return $this;
    }

    public function getCivilite(): ?string
    {
        return $this->civility?->label();
    }

    public function getCiviliteShort(): ?string
    {
        return $this->civility?->short();
    }
}
