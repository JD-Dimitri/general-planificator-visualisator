<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Domain;
use Symfony\Component\Validator\Constraints as Assert;

/** Ce que le client a le droit d'écrire sur un domaine. */
final class DomainInput
{
    /** Identifiant choisi par le client, pour créer un domaine hors ligne. */
    #[Assert\Uuid(message: "L'identifiant doit être un UUID.")]
    public ?string $id = null;

    #[Assert\NotBlank(message: 'Donne un nom au domaine.')]
    #[Assert\Length(max: 120)]
    public string $name = '';

    #[Assert\Range(min: 0, max: 359, notInRangeMessage: 'La teinte va de 0 à 359.')]
    public int $hue = 220;

    #[Assert\Length(max: 255)]
    public string $goal = '';

    #[Assert\Length(max: 20000)]
    public string $notes = '';

    #[Assert\Range(min: 0, max: 9999)]
    public int $position = 0;

    public static function fromEntity(Domain $domain): self
    {
        $input = new self();
        $input->id = (string) $domain->getId();
        $input->name = $domain->getName();
        $input->hue = $domain->getHue();
        $input->goal = $domain->getGoal();
        $input->notes = $domain->getNotes();
        $input->position = $domain->getPosition();

        return $input;
    }

    public function applyTo(Domain $domain): void
    {
        $domain
            ->setName($this->name)
            ->setHue($this->hue)
            ->setGoal($this->goal)
            ->setNotes($this->notes)
            ->setPosition($this->position);
    }
}
