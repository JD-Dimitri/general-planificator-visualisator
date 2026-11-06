<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\Owned;
use App\Repository\DomainRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Un domaine de vie : Santé, Sport, Formation, Famille…
 * Premier niveau de la hiérarchie GPV.
 */
#[ORM\Entity(repositoryClass: DomainRepository::class)]
#[ORM\Table(name: 'domain')]
#[ORM\Index(name: 'idx_domain_owner_position', columns: ['owner_id', 'position'])]
#[ORM\HasLifecycleCallbacks]
class Domain
{
    use Owned;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'Donne un nom au domaine.')]
    #[Assert\Length(max: 120)]
    #[Groups(['read', 'write'])]
    private string $name = '';

    /** Teinte sur le cercle chromatique (0–359). Donne sa couleur au domaine partout dans GPV. */
    #[ORM\Column(type: 'smallint')]
    #[Assert\Range(min: 0, max: 359)]
    #[Groups(['read', 'write'])]
    private int $hue = 220;

    #[ORM\Column(length: 255)]
    #[Assert\Length(max: 255)]
    #[Groups(['read', 'write'])]
    private string $goal = '';

    #[ORM\Column(type: 'text')]
    #[Groups(['read', 'write'])]
    private string $notes = '';

    #[ORM\Column]
    #[Groups(['read', 'write'])]
    private int $position = 0;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = trim($name);

        return $this;
    }

    public function getHue(): int
    {
        return $this->hue;
    }

    public function setHue(int $hue): static
    {
        $this->hue = $hue;

        return $this;
    }

    public function getGoal(): string
    {
        return $this->goal;
    }

    public function setGoal(string $goal): static
    {
        $this->goal = trim($goal);

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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }
}
