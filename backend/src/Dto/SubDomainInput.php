<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\SubDomain;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Ce que le client a le droit d'écrire sur un sous-domaine.
 *
 * `parentId` à null place le sous-domaine directement sous son domaine ;
 * renseigné, il crée un niveau de plus (Famille › Parents › Maman).
 */
final class SubDomainInput
{
    #[Assert\Uuid]
    public ?string $id = null;

    #[Assert\NotBlank(message: 'Donne un nom au sous-domaine.')]
    #[Assert\Length(max: 120)]
    public string $name = '';

    #[Assert\NotBlank(message: 'Choisis le domaine.')]
    #[Assert\Uuid]
    public ?string $domainId = null;

    #[Assert\Uuid]
    public ?string $parentId = null;

    #[Assert\Choice(choices: SubDomain::TEMPLATES, message: 'Gabarit inconnu.')]
    public string $template = 'tache';

    #[Assert\Length(max: 20000)]
    public string $notes = '';

    #[Assert\Range(min: 0, max: 9999)]
    public int $position = 0;

    public static function fromEntity(SubDomain $subDomain): self
    {
        $input = new self();
        $input->id = (string) $subDomain->getId();
        $input->name = $subDomain->getName();
        $input->domainId = (string) $subDomain->getDomain()->getId();
        $input->parentId = $subDomain->getParentId();
        $input->template = $subDomain->getTemplate();
        $input->notes = $subDomain->getNotes();
        $input->position = $subDomain->getPosition();

        return $input;
    }

    public function applyTo(SubDomain $subDomain): void
    {
        $subDomain
            ->setName($this->name)
            ->setTemplate($this->template)
            ->setNotes($this->notes)
            ->setPosition($this->position);
    }
}
