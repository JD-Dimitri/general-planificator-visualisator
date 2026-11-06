<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\Owned;
use App\Repository\ItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Tout ce qu'il y a à faire : tâche, rendez-vous, cours, examen, séance, démarche.
 * Les champs propres au gabarit du sous-domaine sont rangés dans `fields`.
 */
#[ORM\Entity(repositoryClass: ItemRepository::class)]
#[ORM\Table(name: 'item')]
#[ORM\Index(name: 'idx_item_owner_due', columns: ['owner_id', 'due_date'])]
#[ORM\Index(name: 'idx_item_sub_domain', columns: ['sub_domain_id'])]
#[ORM\HasLifecycleCallbacks]
class Item
{
    use Owned;

    public const STATUSES = ['todo', 'doing', 'wait', 'done'];
    public const PRIORITIES = ['haute', 'normale', 'basse'];
    public const RECURRENCES = ['', 'daily', 'weekdays', 'weekly', 'monthly'];

    #[ORM\ManyToOne(targetEntity: Domain::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Domain $domain;

    #[ORM\ManyToOne(targetEntity: SubDomain::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?SubDomain $subDomain = null;

    /** @var Collection<int, Subtask> */
    #[ORM\OneToMany(targetEntity: Subtask::class, mappedBy: 'item', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    #[Groups(['read'])]
    private Collection $subtasks;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Donne un titre à l'élément.")]
    #[Assert\Length(max: 255)]
    #[Groups(['read', 'write'])]
    private string $title = '';

    #[ORM\Column(length: 10)]
    #[Assert\Choice(choices: self::STATUSES)]
    #[Groups(['read', 'write'])]
    private string $status = 'todo';

    #[ORM\Column(length: 10)]
    #[Assert\Choice(choices: self::PRIORITIES)]
    #[Groups(['read', 'write'])]
    private string $priority = 'normale';

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    #[Groups(['read', 'write'])]
    private ?\DateTimeImmutable $dueDate = null;

    /** Heure du rendez-vous, au format HH:MM. Séparée de la date : beaucoup d'éléments n'ont pas d'heure. */
    #[ORM\Column(length: 5, nullable: true)]
    #[Assert\Regex(pattern: '/^([01]\d|2[0-3]):[0-5]\d$/', message: 'Heure attendue au format HH:MM.')]
    #[Groups(['read', 'write'])]
    private ?string $dueTime = null;

    #[ORM\Column(length: 10)]
    #[Assert\Choice(choices: self::RECURRENCES)]
    #[Groups(['read', 'write'])]
    private string $recurrence = '';

    /**
     * Quantième de référence d'une récurrence mensuelle, fixé à la première
     * occurrence et transmis aux suivantes. Sans lui, « le 31 de chaque mois »
     * ramené au 28 février resterait au 28 les mois d'après.
     */
    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Groups(['read'])]
    private ?int $recurrenceAnchorDay = null;

    /** Progression manuelle, utilisée seulement quand l'élément n'a pas de sous-étapes. */
    #[ORM\Column(type: 'smallint')]
    #[Assert\Range(min: 0, max: 100)]
    #[Groups(['read', 'write'])]
    private int $manualProgress = 0;

    /** Champs du gabarit : praticien, lieu, organisme, référence, montant… */
    #[ORM\Column(type: 'json')]
    #[Groups(['read', 'write'])]
    private array $fields = [];

    #[ORM\Column(type: 'text')]
    #[Groups(['read', 'write'])]
    private string $notes = '';

    /** Minutes de concentration cumulées, alimentées par le mode Focus. */
    #[ORM\Column]
    #[Groups(['read'])]
    private int $focusMinutes = 0;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['read'])]
    private ?\DateTimeImmutable $completedAt = null;

    /** Vrai pour les éléments d'exemple livrés avec un compte neuf. */
    #[ORM\Column]
    #[Groups(['read'])]
    private bool $sample = false;

    /**
     * Vrai dès que cet élément a engendré son occurrence suivante.
     *
     * Sans ce drapeau, rouvrir puis reterminer une tâche récurrente créerait
     * une deuxième copie à la même date.
     */
    #[ORM\Column]
    #[Groups(['read'])]
    private bool $recurrenceSpawned = false;

    public function __construct(?Uuid $id = null)
    {
        $this->initOwned($id);
        $this->subtasks = new ArrayCollection();
    }

    public function getDomain(): Domain
    {
        return $this->domain;
    }

    public function setDomain(Domain $domain): static
    {
        $this->domain = $domain;

        return $this;
    }

    #[Groups(['read'])]
    public function getDomainId(): string
    {
        return (string) $this->domain->getId();
    }

    public function getSubDomain(): ?SubDomain
    {
        return $this->subDomain;
    }

    public function setSubDomain(?SubDomain $subDomain): static
    {
        $this->subDomain = $subDomain;

        return $this;
    }

    #[Groups(['read'])]
    public function getSubDomainId(): ?string
    {
        return null !== $this->subDomain ? (string) $this->subDomain->getId() : null;
    }

    /** @return Collection<int, Subtask> */
    public function getSubtasks(): Collection
    {
        return $this->subtasks;
    }

    public function addSubtask(Subtask $subtask): static
    {
        if (!$this->subtasks->contains($subtask)) {
            $this->subtasks->add($subtask);
            $subtask->setItem($this);
        }

        return $this;
    }

    public function removeSubtask(Subtask $subtask): static
    {
        $this->subtasks->removeElement($subtask);

        return $this;
    }

    public function clearSubtasks(): static
    {
        $this->subtasks->clear();

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = trim($title);

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        if ($status === $this->status) {
            return $this;
        }

        $this->status = $status;
        $this->completedAt = 'done' === $status ? new \DateTimeImmutable() : null;

        return $this;
    }

    public function isDone(): bool
    {
        return 'done' === $this->status;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function setPriority(string $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeImmutable $dueDate): static
    {
        $this->dueDate = $dueDate;

        return $this;
    }

    public function getDueTime(): ?string
    {
        return $this->dueTime;
    }

    public function setDueTime(?string $dueTime): static
    {
        $this->dueTime = '' === $dueTime ? null : $dueTime;

        return $this;
    }

    public function getRecurrence(): string
    {
        return $this->recurrence;
    }

    public function setRecurrence(string $recurrence): static
    {
        $this->recurrence = $recurrence;

        return $this;
    }

    public function getRecurrenceAnchorDay(): ?int
    {
        return $this->recurrenceAnchorDay;
    }

    public function setRecurrenceAnchorDay(?int $day): static
    {
        $this->recurrenceAnchorDay = $day;

        return $this;
    }

    /**
     * Fixe le quantième de référence à la première occurrence d'une récurrence
     * mensuelle, et l'efface dès que l'élément cesse de se répéter ainsi.
     */
    public function syncRecurrenceAnchor(): static
    {
        if ('monthly' !== $this->recurrence) {
            $this->recurrenceAnchorDay = null;
        } elseif (null === $this->recurrenceAnchorDay && null !== $this->dueDate) {
            $this->recurrenceAnchorDay = (int) $this->dueDate->format('j');
        }

        return $this;
    }

    public function getManualProgress(): int
    {
        return $this->manualProgress;
    }

    public function setManualProgress(int $manualProgress): static
    {
        $this->manualProgress = $manualProgress;

        return $this;
    }

    /**
     * Avec des sous-étapes, la progression se calcule toute seule ;
     * sans elles, c'est le curseur manuel qui décide.
     */
    #[Groups(['read'])]
    public function getProgress(): int
    {
        if ($this->subtasks->isEmpty()) {
            return $this->isDone() ? 100 : $this->manualProgress;
        }

        $done = 0;
        foreach ($this->subtasks as $subtask) {
            if ($subtask->isDone()) {
                ++$done;
            }
        }

        return (int) round(100 * $done / \count($this->subtasks));
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function setFields(array $fields): static
    {
        $this->fields = array_filter($fields, static fn ($v) => '' !== $v && null !== $v);

        return $this;
    }

    public function getNotes(): string
    {
        return $this->notes;
    }

    public function setNotes(string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getFocusMinutes(): int
    {
        return $this->focusMinutes;
    }

    public function addFocusMinutes(int $minutes): static
    {
        $this->focusMinutes += max(0, $minutes);

        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function isSample(): bool
    {
        return $this->sample;
    }

    public function setSample(bool $sample): static
    {
        $this->sample = $sample;

        return $this;
    }

    public function hasSpawnedRecurrence(): bool
    {
        return $this->recurrenceSpawned;
    }

    public function markRecurrenceSpawned(): static
    {
        $this->recurrenceSpawned = true;

        return $this;
    }
}
