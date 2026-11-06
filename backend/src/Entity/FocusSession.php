<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\Owned;
use App\Repository\FocusSessionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Une séance de concentration terminée : la brique des statistiques du mode Focus
 * (temps par jour, par semaine et par domaine).
 */
#[ORM\Entity(repositoryClass: FocusSessionRepository::class)]
#[ORM\Table(name: 'focus_session')]
#[ORM\Index(name: 'idx_focus_owner_day', columns: ['owner_id', 'day'])]
#[ORM\HasLifecycleCallbacks]
class FocusSession
{
    use Owned;

    /** Jour local de la séance : les statistiques se lisent par journée, pas par instant UTC. */
    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull]
    #[Groups(['read', 'write'])]
    private ?\DateTimeImmutable $day = null;

    #[ORM\Column]
    #[Assert\Positive]
    #[Assert\LessThanOrEqual(600)]
    #[Groups(['read', 'write'])]
    private int $minutes = 0;

    #[ORM\Column(length: 10)]
    #[Assert\Choice(choices: ['pomodoro', 'chrono'])]
    #[Groups(['read', 'write'])]
    private string $mode = 'pomodoro';

    #[ORM\ManyToOne(targetEntity: Domain::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Domain $domain = null;

    #[ORM\ManyToOne(targetEntity: Item::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Item $item = null;

    public function getDay(): ?\DateTimeImmutable
    {
        return $this->day;
    }

    public function setDay(?\DateTimeImmutable $day): static
    {
        $this->day = $day;

        return $this;
    }

    public function getMinutes(): int
    {
        return $this->minutes;
    }

    public function setMinutes(int $minutes): static
    {
        $this->minutes = $minutes;

        return $this;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function setMode(string $mode): static
    {
        $this->mode = $mode;

        return $this;
    }

    public function getDomain(): ?Domain
    {
        return $this->domain;
    }

    public function setDomain(?Domain $domain): static
    {
        $this->domain = $domain;

        return $this;
    }

    #[Groups(['read'])]
    public function getDomainId(): ?string
    {
        return null !== $this->domain ? (string) $this->domain->getId() : null;
    }

    public function getItem(): ?Item
    {
        return $this->item;
    }

    public function setItem(?Item $item): static
    {
        $this->item = $item;

        return $this;
    }

    #[Groups(['read'])]
    public function getItemId(): ?string
    {
        return null !== $this->item ? (string) $this->item->getId() : null;
    }
}
