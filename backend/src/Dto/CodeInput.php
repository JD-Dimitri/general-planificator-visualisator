<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Deuxième étape : le code reçu par e-mail ou par SMS. */
final class CodeInput
{
    #[Assert\NotBlank(message: 'Indique le code reçu.')]
    #[Assert\Uuid(message: 'Demande de connexion inconnue. Recommence.')]
    public string $challengeId = '';

    #[Assert\NotBlank(message: 'Saisis le code à six chiffres.')]
    #[Assert\Regex(pattern: '/^\d{6}$/', message: 'Le code comporte six chiffres.')]
    public string $code = '';
}
