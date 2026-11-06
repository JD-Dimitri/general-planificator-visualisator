<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\LoginCode;
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

    /** Facultatif. Renseigné, il permet de recevoir le code par SMS. */
    #[Assert\Regex(pattern: '/^\+[1-9]\d{6,17}$/', message: 'Numéro attendu au format international, par exemple +33612345678.')]
    public ?string $phone = null;

    #[Assert\Choice(choices: [LoginCode::CHANNEL_EMAIL, LoginCode::CHANNEL_SMS], message: 'Canal inconnu.')]
    public string $channel = LoginCode::CHANNEL_EMAIL;
}
