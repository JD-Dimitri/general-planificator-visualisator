<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Shift;
use Symfony\Component\Validator\Constraints as Assert;

/** Une vacation. Une fin antérieure au début signifie simplement une nuit. */
final class ShiftInput
{
    #[Assert\Uuid]
    public ?string $id = null;

    #[Assert\NotBlank(message: 'Choisis une date.')]
    #[Assert\Date(message: 'Date attendue au format AAAA-MM-JJ.')]
    public ?string $date = null;

    #[Assert\Regex(pattern: '/^([01]\d|2[0-3]):[0-5]\d$/', message: 'Heure de début attendue au format HH:MM.')]
    public string $startTime = '19:00';

    #[Assert\Regex(pattern: '/^([01]\d|2[0-3]):[0-5]\d$/', message: 'Heure de fin attendue au format HH:MM.')]
    public string $endTime = '07:00';

    #[Assert\Length(max: 160)]
    public string $place = '';

    public static function fromEntity(Shift $shift): self
    {
        $input = new self();
        $input->id = (string) $shift->getId();
        $input->date = $shift->getDate()?->format('Y-m-d');
        $input->startTime = $shift->getStartTime();
        $input->endTime = $shift->getEndTime();
        $input->place = $shift->getPlace();

        return $input;
    }

    public function applyTo(Shift $shift): void
    {
        $shift
            ->setDate(null !== $this->date ? new \DateTimeImmutable($this->date) : null)
            ->setStartTime($this->startTime)
            ->setEndTime($this->endTime)
            ->setPlace($this->place);
    }
}
