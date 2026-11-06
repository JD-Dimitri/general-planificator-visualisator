<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\LoginCode;
use Symfony\Component\Validator\Constraints as Assert;

/** Première étape de la connexion : adresse et mot de passe, sans session à la clé. */
final class CredentialsInput
{
    #[Assert\NotBlank(message: 'Indique ton adresse e-mail.')]
    #[Assert\Length(max: 180)]
    public string $email = '';

    #[Assert\NotBlank(message: 'Indique ton mot de passe.')]
    #[Assert\Length(max: 4096)]
    public string $password = '';

    /** Par où recevoir le code. Le SMS demande un numéro enregistré. */
    #[Assert\Choice(choices: [LoginCode::CHANNEL_EMAIL, LoginCode::CHANNEL_SMS], message: 'Canal inconnu.')]
    public string $channel = LoginCode::CHANNEL_EMAIL;
}
