<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Une étape à cocher, envoyée dans le tableau `subtasks` d'un élément. */
final class SubtaskInput
{
    #[Assert\Uuid]
    public ?string $id = null;

    #[Assert\NotBlank(message: "Une étape sans texte n'a rien à cocher.")]
    #[Assert\Length(max: 255)]
    public string $label = '';

    public bool $done = false;
}
