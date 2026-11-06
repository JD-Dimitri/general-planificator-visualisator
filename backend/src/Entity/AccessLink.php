<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\Owned;
use App\Repository\AccessLinkRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Un accès direct : Doctolib dans Santé, la plateforme de formation, le coffre de mots de passe.
 *
 * GPV n'enregistre jamais de mot de passe, seulement l'adresse et l'identifiant.
 * L'adresse est contrainte à http(s) : un lien « javascript: » enregistré ici
 * s'exécuterait dans la page au clic.
 */
#[ORM\Entity(repositoryClass: AccessLinkRepository::class)]
#[ORM\Table(name: 'access_link')]
#[ORM\HasLifecycleCallbacks]
class AccessLink
{
    use Owned;

    #[ORM\ManyToOne(targetEntity: Domain::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Domain $domain = null;

    #[ORM\ManyToOne(targetEntity: SubDomain::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?SubDomain $subDomain = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: "Donne un nom à l'accès.")]
    #[Assert\Length(max: 120)]
    #[Groups(['read', 'write'])]
    private string $label = '';

    #[ORM\Column(length: 2048)]
    #[Assert\NotBlank]
    #[Assert\Url(protocols: ['http', 'https'], message: "L'adresse doit commencer par https:// ou http://.")]
    #[Groups(['read', 'write'])]
    private string $url = '';

    #[ORM\Column(length: 180)]
    #[Assert\Length(max: 180)]
    #[Groups(['read', 'write'])]
    private string $login = '';

    #[ORM\Column]
    #[Groups(['read', 'write'])]
    private int $position = 0;

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

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = trim($label);

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = trim($url);

        return $this;
    }

    public function getLogin(): string
    {
        return $this->login;
    }

    public function setLogin(string $login): static
    {
        $this->login = trim($login);

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
