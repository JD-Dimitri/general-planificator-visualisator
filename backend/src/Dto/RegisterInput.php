<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class RegisterInput
{
    #[Assert\NotBlank(message: 'Indique ton adresse e-mail.')]
    #[Assert\Email(message: "Cette adresse e-mail n'est pas valide.")]
    #[Assert\Length(max: 180)]
    public string $email = '';

    #[Assert\NotBlank(message: 'Choisis un mot de passe.')]
    #[Assert\Length(min: 10, max: 4096, minMessage: 'Le mot de passe doit faire au moins {{ limit }} caractères.')]
    #[Assert\NotCompromisedPassword(message: 'Ce mot de passe figure dans des fuites connues. Choisis-en un autre.')]
    public string $password = '';

    #[Assert\Length(max: 120)]
    public string $displayName = '';
}
