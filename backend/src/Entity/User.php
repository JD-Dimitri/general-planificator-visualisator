<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'Un compte existe déjà avec cette adresse.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const PLAN_FREE = 'free';
    public const PLAN_CONNECTED = 'connected';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['read'])]
    private Uuid $id;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Groups(['read'])]
    private string $email;

    /** @var list<string> */
    #[ORM\Column]
    #[Groups(['read'])]
    private array $roles = [];

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    #[Groups(['read', 'write'])]
    private string $displayName = '';

    /**
     * Offre du compte. Verrouille les fonctions connectées (import par IA,
     * messagerie, sauvegarde cloud) décrites dans le cahier des charges.
     */
    #[ORM\Column(length: 20)]
    #[Groups(['read'])]
    private string $plan = self::PLAN_FREE;

    /**
     * Numéro de téléphone, au format international. Sert de second canal pour
     * le code à usage unique quand l'utilisateur en fournit un.
     */
    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Regex(pattern: '/^\\+[1-9]\\d{6,17}$/', message: 'Numéro attendu au format international, par exemple +33612345678.')]
    #[Groups(['read', 'write'])]
    private ?string $phone = null;

    /**
     * Date de validation du compte par code à usage unique. Tant qu'elle est
     * nulle, le compte existe mais n'a jamais prouvé l'accès à sa boîte mail.
     */
    #[ORM\Column(nullable: true)]
    #[Groups(['read'])]
    private ?\DateTimeImmutable $verifiedAt = null;

    /** Préférences d'interface : thème, ambiance, durées du minuteur. */
    #[ORM\Column(type: 'json')]
    #[Groups(['read', 'write'])]
    private array $settings = [];

    #[ORM\Column]
    #[Groups(['read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct(?Uuid $id = null)
    {
        $this->id = $id ?? Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): static
    {
        $this->displayName = $displayName;

        return $this;
    }

    public function getPlan(): string
    {
        return $this->plan;
    }

    public function setPlan(string $plan): static
    {
        $this->plan = $plan;

        return $this;
    }

    public function hasConnectedPlan(): bool
    {
        return self::PLAN_CONNECTED === $this->plan;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $phone = null !== $phone ? preg_replace('/[\s.-]/', '', trim($phone)) : null;
        $this->phone = '' === $phone ? null : $phone;

        return $this;
    }

    public function getVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    #[Groups(['read'])]
    public function isVerified(): bool
    {
        return null !== $this->verifiedAt;
    }

    public function markVerified(): static
    {
        $this->verifiedAt ??= new \DateTimeImmutable();

        return $this;
    }

    public function getSettings(): array
    {
        return $this->settings;
    }

    public function setSettings(array $settings): static
    {
        $this->settings = $settings;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function eraseCredentials(): void
    {
    }
}
