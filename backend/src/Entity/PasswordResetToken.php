<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PasswordResetTokenRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Jeton de réinitialisation de mot de passe, envoyé par lien dans un e-mail.
 *
 * Trente-deux octets aléatoires, dont seule l'empreinte SHA-256 est enregistrée,
 * valables une heure et une seule fois. Changer de mot de passe révoque les
 * autres jetons et déconnecte les appareils : si quelqu'un s'était introduit,
 * la réinitialisation le met dehors.
 */
#[ORM\Entity(repositoryClass: PasswordResetTokenRepository::class)]
#[ORM\Table(name: 'password_reset_token')]
#[ORM\Index(name: 'idx_password_reset_user', columns: ['user_id'])]
class PasswordResetToken
{
    public const TTL_SECONDS = 3600;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    public function __construct(User $user, string $tokenHash, \DateTimeImmutable $expiresAt)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->tokenHash = $tokenHash;
        $this->expiresAt = $expiresAt;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isUsed(): bool
    {
        return null !== $this->usedAt;
    }

    public function use(): static
    {
        $this->usedAt ??= new \DateTimeImmutable();

        return $this;
    }

    public function isUsable(): bool
    {
        return !$this->isUsed() && $this->expiresAt > new \DateTimeImmutable();
    }
}
