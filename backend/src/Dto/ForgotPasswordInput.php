<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class ForgotPasswordInput
{
    #[Assert\NotBlank(message: 'Indique ton adresse e-mail.')]
    #[Assert\Email(message: "Cette adresse e-mail n'est pas valide.")]
    #[Assert\Length(max: 180)]
    public string $email = '';
}
