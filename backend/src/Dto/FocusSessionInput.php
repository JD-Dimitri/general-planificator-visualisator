<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Une séance de concentration terminée.
 *
 * Le jour est envoyé par le client : c'est son jour local qui compte pour les
 * statistiques, pas celui du serveur. Une séance finie à 1 h du matin à Paris
 * appartient à la nuit qui s'achève, pas à la veille en temps universel.
 */
final class FocusSessionInput
{
    #[Assert\Uuid]
    public ?string $id = null;

    #[Assert\NotBlank(message: 'Indique le jour de la séance.')]
    #[Assert\Date(message: 'Jour attendu au format AAAA-MM-JJ.')]
    public ?string $day = null;

    #[Assert\Positive(message: 'Une séance dure au moins une minute.')]
    #[Assert\LessThanOrEqual(value: 600, message: 'Une séance ne peut pas dépasser 10 heures.')]
    public int $minutes = 0;

    #[Assert\Choice(choices: ['pomodoro', 'chrono'], message: 'Mode inconnu.')]
    public string $mode = 'pomodoro';

    #[Assert\Uuid]
    public ?string $itemId = null;

    #[Assert\Uuid]
    public ?string $domainId = null;
}
