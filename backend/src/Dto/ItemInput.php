<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Item;
use App\Entity\Subtask;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Ce que le client a le droit d'écrire sur un élément.
 *
 * Les dates circulent en chaînes « AAAA-MM-JJ » plutôt qu'en objets : une
 * échéance est un jour du calendrier, pas un instant, et la convertir en
 * horodatage lui ferait changer de jour d'un fuseau à l'autre.
 */
final class ItemInput
{
    #[Assert\Uuid]
    public ?string $id = null;

    #[Assert\NotBlank(message: "Donne un titre à l'élément.")]
    #[Assert\Length(max: 255)]
    public string $title = '';

    #[Assert\NotBlank(message: 'Choisis le domaine.')]
    #[Assert\Uuid]
    public ?string $domainId = null;

    #[Assert\Uuid]
    public ?string $subDomainId = null;

    #[Assert\Choice(choices: Item::STATUSES, message: 'Statut inconnu.')]
    public string $status = 'todo';

    #[Assert\Choice(choices: Item::PRIORITIES, message: 'Priorité inconnue.')]
    public string $priority = 'normale';

    #[Assert\Date(message: 'Échéance attendue au format AAAA-MM-JJ.')]
    public ?string $dueDate = null;

    #[Assert\Regex(pattern: '/^([01]\d|2[0-3]):[0-5]\d$/', message: 'Heure attendue au format HH:MM.')]
    public ?string $dueTime = null;

    #[Assert\Choice(choices: Item::RECURRENCES, message: 'Récurrence inconnue.')]
    public string $recurrence = '';

    #[Assert\Range(min: 0, max: 100)]
    public int $manualProgress = 0;

    /** @var array<string, string> */
    #[Assert\Count(max: 30, maxMessage: 'Un élément ne peut pas porter plus de {{ limit }} champs.')]
    #[Assert\All([new Assert\Type('string'), new Assert\Length(max: 2000)])]
    public array $fields = [];

    #[Assert\Length(max: 20000)]
    public string $notes = '';

    /**
     * Les étapes sont remplacées en bloc : le client envoie la liste complète.
     * Une étape qui garde son identifiant garde son état ; une étape absente
     * de la liste est supprimée.
     *
     * @var list<SubtaskInput>
     */
    #[Assert\Valid]
    #[Assert\Count(max: 60, maxMessage: 'Pas plus de {{ limit }} étapes par élément.')]
    public array $subtasks = [];

    public static function fromEntity(Item $item): self
    {
        $input = new self();
        $input->id = (string) $item->getId();
        $input->title = $item->getTitle();
        $input->domainId = (string) $item->getDomain()->getId();
        $input->subDomainId = $item->getSubDomainId();
        $input->status = $item->getStatus();
        $input->priority = $item->getPriority();
        $input->dueDate = $item->getDueDate()?->format('Y-m-d');
        $input->dueTime = $item->getDueTime();
        $input->recurrence = $item->getRecurrence();
        $input->manualProgress = $item->getManualProgress();
        $input->fields = array_map(static fn ($value) => (string) $value, $item->getFields());
        $input->notes = $item->getNotes();

        foreach ($item->getSubtasks() as $subtask) {
            $step = new SubtaskInput();
            $step->id = (string) $subtask->getId();
            $step->label = $subtask->getLabel();
            $step->done = $subtask->isDone();
            $input->subtasks[] = $step;
        }

        return $input;
    }

    /** N'applique pas le statut : le contrôleur le passe par `setStatus` pour gérer la récurrence. */
    public function applyTo(Item $item): void
    {
        $item
            ->setTitle($this->title)
            ->setPriority($this->priority)
            ->setDueDate(null !== $this->dueDate && '' !== $this->dueDate ? new \DateTimeImmutable($this->dueDate) : null)
            ->setDueTime($this->dueTime)
            ->setRecurrence($this->recurrence)
            ->setManualProgress($this->manualProgress)
            ->setFields($this->fields)
            ->setNotes($this->notes);

        $item->syncRecurrenceAnchor();

        $this->applySubtasks($item);
    }

    private function applySubtasks(Item $item): void
    {
        $existing = [];
        foreach ($item->getSubtasks() as $subtask) {
            $existing[(string) $subtask->getId()] = $subtask;
        }

        $item->clearSubtasks();

        foreach ($this->subtasks as $position => $step) {
            $subtask = (null !== $step->id && isset($existing[$step->id]))
                ? $existing[$step->id]
                : new Subtask(null !== $step->id && Uuid::isValid($step->id) ? Uuid::fromString($step->id) : null);

            $subtask->setLabel($step->label)->setDone($step->done)->setPosition($position + 1);
            $item->addSubtask($subtask);
        }
    }
}
