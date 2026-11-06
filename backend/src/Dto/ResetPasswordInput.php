<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class ResetPasswordInput
{
    #[Assert\NotBlank(message: 'Lien de réinitialisation incomplet.')]
    #[Assert\Length(min: 16, max: 128)]
    public string $token = '';

    #[Assert\NotBlank(message: 'Choisis un nouveau mot de passe.')]
    #[Assert\Length(min: 10, max: 4096, minMessage: 'Le mot de passe doit faire au moins {{ limit }} caractères.')]
    #[Assert\NotCompromisedPassword(message: 'Ce mot de passe figure dans des fuites connues. Choisis-en un autre.')]
    public string $password = '';
}
