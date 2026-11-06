<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\Owned;
use App\Repository\ShiftRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Une vacation de sécurité.
 *
 * Une vacation de nuit (19:00 → 07:00) a une heure de fin antérieure à son début :
 * `spansMidnight()` le signale pour que le planning l'affiche aussi le lendemain.
 */
#[ORM\Entity(repositoryClass: ShiftRepository::class)]
#[ORM\Table(name: 'shift')]
#[ORM\Index(name: 'idx_shift_owner_date', columns: ['owner_id', 'date'])]
#[ORM\HasLifecycleCallbacks]
class Shift
{
    use Owned;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull(message: 'Choisis une date.')]
    #[Groups(['read', 'write'])]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(length: 5)]
    #[Assert\Regex(pattern: '/^([01]\d|2[0-3]):[0-5]\d$/')]
    #[Groups(['read', 'write'])]
    private string $startTime = '19:00';

    #[ORM\Column(length: 5)]
    #[Assert\Regex(pattern: '/^([01]\d|2[0-3]):[0-5]\d$/')]
    #[Groups(['read', 'write'])]
    private string $endTime = '07:00';

    #[ORM\Column(length: 160)]
    #[Assert\Length(max: 160)]
    #[Groups(['read', 'write'])]
    private string $place = '';

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(?\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getStartTime(): string
    {
        return $this->startTime;
    }

    public function setStartTime(string $startTime): static
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function getEndTime(): string
    {
        return $this->endTime;
    }

    public function setEndTime(string $endTime): static
    {
        $this->endTime = $endTime;

        return $this;
    }

    #[Groups(['read'])]
    public function spansMidnight(): bool
    {
        return $this->endTime <= $this->startTime;
    }

    public function getPlace(): string
    {
        return $this->place;
    }

    public function setPlace(string $place): static
    {
        $this->place = trim($place);

        return $this;
    }
}
