<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LoginCodeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Code à usage unique envoyé par e-mail ou par SMS.
 *
 * Il est exigé à la création du compte et à chaque connexion : connaître le
 * mot de passe ne suffit pas, il faut aussi accéder à la boîte mail ou au
 * téléphone.
 *
 * Seule l'empreinte du code est enregistrée. Six chiffres se cassent en une
 * seconde hors ligne, alors le compteur de tentatives et la durée de vie font
 * l'essentiel du travail : cinq essais, dix minutes, puis le code est mort.
 */
#[ORM\Entity(repositoryClass: LoginCodeRepository::class)]
#[ORM\Table(name: 'login_code')]
#[ORM\Index(name: 'idx_login_code_user', columns: ['user_id'])]
class LoginCode
{
    public const PURPOSE_REGISTRATION = 'registration';
    public const PURPOSE_LOGIN = 'login';
    public const PURPOSE_TOKEN = 'token';

    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_SMS = 'sms';

    public const MAX_ATTEMPTS = 5;
    public const TTL_SECONDS = 600;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 20)]
    private string $purpose;

    #[ORM\Column(length: 10)]
    private string $channel;

    /** Adresse ou numéro masqué, montré à l'utilisateur : « d••••@gmail.com ». */
    #[ORM\Column(length: 180)]
    private string $maskedDestination;

    #[ORM\Column(length: 64)]
    private string $codeHash;

    #[ORM\Column]
    private int $attempts = 0;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $consumedAt = null;

    public function __construct(
        User $user,
        string $purpose,
        string $channel,
        string $maskedDestination,
        string $codeHash,
        \DateTimeImmutable $expiresAt,
    ) {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->purpose = $purpose;
        $this->channel = $channel;
        $this->maskedDestination = $maskedDestination;
        $this->codeHash = $codeHash;
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

    public function getPurpose(): string
    {
        return $this->purpose;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function getMaskedDestination(): string
    {
        return $this->maskedDestination;
    }

    public function getCodeHash(): string
    {
        return $this->codeHash;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function recordAttempt(): static
    {
        ++$this->attempts;

        return $this;
    }

    public function consume(): static
    {
        $this->consumedAt ??= new \DateTimeImmutable();

        return $this;
    }

    public function isConsumed(): bool
    {
        return null !== $this->consumedAt;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt <= new \DateTimeImmutable();
    }

    public function isExhausted(): bool
    {
        return $this->attempts >= self::MAX_ATTEMPTS;
    }

    public function isUsable(): bool
    {
        return !$this->isConsumed() && !$this->isExpired() && !$this->isExhausted();
    }

    public function secondsLeft(): int
    {
        return max(0, $this->expiresAt->getTimestamp() - time());
    }
}
