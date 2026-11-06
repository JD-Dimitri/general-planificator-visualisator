<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\Owned;
use App\Repository\InboxEntryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Une ligne en attente de classement dans la boîte de dépôt.
 *
 * Elle arrive d'un fichier importé (texte, Word, PDF, Excel) ou d'un bloc-notes,
 * avec un domaine et une date proposés. Rangée, elle devient un Item et disparaît.
 */
#[ORM\Entity(repositoryClass: InboxEntryRepository::class)]
#[ORM\Table(name: 'inbox_entry')]
#[ORM\HasLifecycleCallbacks]
class InboxEntry
{
    use Owned;

    #[ORM\Column(length: 300)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 300)]
    #[Groups(['read', 'write'])]
    private string $text = '';

    /** Nom du fichier ou origine de la ligne, affiché pour aider au classement. */
    #[ORM\Column(length: 180)]
    #[Groups(['read', 'write'])]
    private string $source = '';

    #[ORM\ManyToOne(targetEntity: Domain::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Domain $suggestedDomain = null;

    #[ORM\ManyToOne(targetEntity: SubDomain::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?SubDomain $suggestedSubDomain = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    #[Groups(['read', 'write'])]
    private ?\DateTimeImmutable $suggestedDueDate = null;

    #[ORM\Column(length: 5, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $suggestedDueTime = null;

    /** Vrai quand le classement vient d'une règle automatique et non d'un choix de l'utilisateur. */
    #[ORM\Column]
    #[Groups(['read'])]
    private bool $autoClassified = false;

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = trim($text);

        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getSuggestedDomain(): ?Domain
    {
        return $this->suggestedDomain;
    }

    public function setSuggestedDomain(?Domain $domain): static
    {
        $this->suggestedDomain = $domain;

        return $this;
    }

    #[Groups(['read'])]
    public function getSuggestedDomainId(): ?string
    {
        return null !== $this->suggestedDomain ? (string) $this->suggestedDomain->getId() : null;
    }

    public function getSuggestedSubDomain(): ?SubDomain
    {
        return $this->suggestedSubDomain;
    }

    public function setSuggestedSubDomain(?SubDomain $subDomain): static
    {
        $this->suggestedSubDomain = $subDomain;

        return $this;
    }

    #[Groups(['read'])]
    public function getSuggestedSubDomainId(): ?string
    {
        return null !== $this->suggestedSubDomain ? (string) $this->suggestedSubDomain->getId() : null;
    }

    public function getSuggestedDueDate(): ?\DateTimeImmutable
    {
        return $this->suggestedDueDate;
    }

    public function setSuggestedDueDate(?\DateTimeImmutable $date): static
    {
        $this->suggestedDueDate = $date;

        return $this;
    }

    public function getSuggestedDueTime(): ?string
    {
        return $this->suggestedDueTime;
    }

    public function setSuggestedDueTime(?string $time): static
    {
        $this->suggestedDueTime = '' === $time ? null : $time;

        return $this;
    }

    public function isAutoClassified(): bool
    {
        return $this->autoClassified;
    }

    public function setAutoClassified(bool $autoClassified): static
    {
        $this->autoClassified = $autoClassified;

        return $this;
    }
}
