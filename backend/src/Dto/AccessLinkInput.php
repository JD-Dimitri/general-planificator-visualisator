<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\AccessLink;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Un accès direct. L'adresse est contrainte à http(s) : une adresse
 * « javascript: » enregistrée ici s'exécuterait au clic dans la page.
 * Le mot de passe n'a volontairement pas de champ.
 */
final class AccessLinkInput
{
    #[Assert\Uuid]
    public ?string $id = null;

    #[Assert\NotBlank(message: "Donne un nom à l'accès.")]
    #[Assert\Length(max: 120)]
    public string $label = '';

    #[Assert\NotBlank(message: "Indique l'adresse du site.")]
    #[Assert\Url(protocols: ['http', 'https'], message: "L'adresse doit commencer par https:// ou http://.")]
    #[Assert\Length(max: 2048)]
    public string $url = '';

    #[Assert\Length(max: 180)]
    public string $login = '';

    #[Assert\Uuid]
    public ?string $domainId = null;

    #[Assert\Uuid]
    public ?string $subDomainId = null;

    #[Assert\Range(min: 0, max: 9999)]
    public int $position = 0;

    public static function fromEntity(AccessLink $link): self
    {
        $input = new self();
        $input->id = (string) $link->getId();
        $input->label = $link->getLabel();
        $input->url = $link->getUrl();
        $input->login = $link->getLogin();
        $input->domainId = $link->getDomainId();
        $input->subDomainId = $link->getSubDomainId();
        $input->position = $link->getPosition();

        return $input;
    }

    public function applyTo(AccessLink $link): void
    {
        $link
            ->setLabel($this->label)
            ->setUrl($this->url)
            ->setLogin($this->login)
            ->setPosition($this->position);
    }
}
