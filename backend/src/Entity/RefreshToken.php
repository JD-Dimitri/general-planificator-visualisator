<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Jeton de rafraîchissement, pour les clients qui n'utilisent pas de cookie
 * (application mobile ou de bureau).
 *
 * Seule l'empreinte SHA-256 est enregistrée : une copie de la base ne permet pas
 * de rejouer les jetons. Chaque rafraîchissement révoque le jeton présenté et en
 * émet un nouveau, ce qui rend un jeton volé détectable et sans valeur durable.
 */
#[ORM\Entity(repositoryClass: RefreshTokenRepository::class)]
#[ORM\Table(name: 'refresh_token')]
#[ORM\Index(name: 'idx_refresh_token_hash', columns: ['token_hash'])]
class RefreshToken
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\Column(length: 255)]
    private string $userAgent = '';

    public function __construct(User $owner, string $tokenHash, \DateTimeImmutable $expiresAt, string $userAgent = '')
    {
        $this->id = Uuid::v7();
        $this->owner = $owner;
        $this->tokenHash = $tokenHash;
        $this->expiresAt = $expiresAt;
        $this->createdAt = new \DateTimeImmutable();
        $this->userAgent = mb_substr($userAgent, 0, 255);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
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

    public function isRevoked(): bool
    {
        return null !== $this->revokedAt;
    }

    public function revoke(): static
    {
        $this->revokedAt ??= new \DateTimeImmutable();

        return $this;
    }

    public function isUsable(): bool
    {
        return !$this->isRevoked() && $this->expiresAt > new \DateTimeImmutable();
    }

    public function getUserAgent(): string
    {
        return $this->userAgent;
    }
}
