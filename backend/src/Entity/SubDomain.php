<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\Owned;
use App\Repository\SubDomainRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Un sous-domaine, imbriquable sans limite de profondeur :
 * Famille › Parents › Maman.
 *
 * `domain` est toujours renseigné, même sur un enfant : cela évite de remonter
 * la chaîne des parents pour connaître la couleur et le domaine d'un élément.
 * La cohérence entre `parent->domain` et `domain` est tenue par le contrôleur.
 */
#[ORM\Entity(repositoryClass: SubDomainRepository::class)]
#[ORM\Table(name: 'sub_domain')]
#[ORM\Index(name: 'idx_sub_domain_parent', columns: ['parent_id'])]
#[ORM\Index(name: 'idx_sub_domain_domain', columns: ['domain_id', 'position'])]
#[ORM\HasLifecycleCallbacks]
class SubDomain
{
    use Owned;

    /** Gabarits : jeux de champs propres à chaque cadre. */
    public const TEMPLATES = ['tache', 'rdv', 'cours', 'formation', 'programme', 'habitude', 'demarche'];

    #[ORM\ManyToOne(targetEntity: Domain::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Domain $domain;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?self $parent = null;

    /** @var Collection<int, self> */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $children;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'Donne un nom au sous-domaine.')]
    #[Assert\Length(max: 120)]
    #[Groups(['read', 'write'])]
    private string $name = '';

    #[ORM\Column(length: 30)]
    #[Assert\Choice(choices: self::TEMPLATES, message: 'Gabarit inconnu.')]
    #[Groups(['read', 'write'])]
    private string $template = 'tache';

    #[ORM\Column(type: 'text')]
    #[Groups(['read', 'write'])]
    private string $notes = '';

    #[ORM\Column]
    #[Groups(['read', 'write'])]
    private int $position = 0;

    public function __construct(?Uuid $id = null)
    {
        $this->initOwned($id);
        $this->children = new ArrayCollection();
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

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    #[Groups(['read'])]
    public function getParentId(): ?string
    {
        return null !== $this->parent ? (string) $this->parent->getId() : null;
    }

    /** @return Collection<int, self> */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    /**
     * Le chemin complet depuis la racine : [Parents, Maman].
     * La garde à 32 niveaux protège d'un cycle introduit par une écriture directe en base.
     *
     * @return list<self>
     */
    public function getPath(): array
    {
        $path = [];
        $node = $this;
        $guard = 0;

        while (null !== $node && $guard++ < 32) {
            array_unshift($path, $node);
            $node = $node->getParent();
        }

        return $path;
    }

    #[Groups(['read'])]
    public function getDepth(): int
    {
        return \count($this->getPath()) - 1;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = trim($name);

        return $this;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function setTemplate(string $template): static
    {
        $this->template = $template;

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
