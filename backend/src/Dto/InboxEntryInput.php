<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\InboxEntry;
use Symfony\Component\Validator\Constraints as Assert;

/** Une ligne déposée, avec le classement proposé qu'on peut corriger avant de ranger. */
final class InboxEntryInput
{
    #[Assert\Uuid]
    public ?string $id = null;

    #[Assert\NotBlank(message: 'Une ligne vide ne donne rien à ranger.')]
    #[Assert\Length(max: 300)]
    public string $text = '';

    #[Assert\Length(max: 180)]
    public string $source = '';

    #[Assert\Uuid]
    public ?string $suggestedDomainId = null;

    #[Assert\Uuid]
    public ?string $suggestedSubDomainId = null;

    #[Assert\Date(message: 'Date attendue au format AAAA-MM-JJ.')]
    public ?string $suggestedDueDate = null;

    #[Assert\Regex(pattern: '/^([01]\d|2[0-3]):[0-5]\d$/', message: 'Heure attendue au format HH:MM.')]
    public ?string $suggestedDueTime = null;

    public bool $autoClassified = false;

    public static function fromEntity(InboxEntry $entry): self
    {
        $input = new self();
        $input->id = (string) $entry->getId();
        $input->text = $entry->getText();
        $input->source = $entry->getSource();
        $input->suggestedDomainId = $entry->getSuggestedDomainId();
        $input->suggestedSubDomainId = $entry->getSuggestedSubDomainId();
        $input->suggestedDueDate = $entry->getSuggestedDueDate()?->format('Y-m-d');
        $input->suggestedDueTime = $entry->getSuggestedDueTime();
        $input->autoClassified = $entry->isAutoClassified();

        return $input;
    }

    public function applyTo(InboxEntry $entry): void
    {
        $entry
            ->setText($this->text)
            ->setSource($this->source)
            ->setSuggestedDueDate(null !== $this->suggestedDueDate && '' !== $this->suggestedDueDate ? new \DateTimeImmutable($this->suggestedDueDate) : null)
            ->setSuggestedDueTime($this->suggestedDueTime)
            ->setAutoClassified($this->autoClassified);
    }
}
