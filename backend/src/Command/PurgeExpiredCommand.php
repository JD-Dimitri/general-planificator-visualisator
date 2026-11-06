<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\LoginCodeRepository;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\RefreshTokenRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Tâche de nuit : les tables de jetons et de codes grossissent sans fin
 * si on ne les nettoie jamais.
 */
#[AsCommand(
    name: 'app:purge-expired',
    description: 'Supprime les jetons et les codes expirés.',
)]
final class PurgeExpiredCommand extends Command
{
    public function __construct(
        private readonly RefreshTokenRepository $refreshTokens,
        private readonly LoginCodeRepository $loginCodes,
        private readonly PasswordResetTokenRepository $resetTokens,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->table(
            ['Table', 'Lignes supprimées'],
            [
                ['Jetons de rafraîchissement', $this->refreshTokens->purgeExpired()],
                ['Codes à usage unique', $this->loginCodes->purgeExpired()],
                ['Liens de réinitialisation', $this->resetTokens->purgeExpired()],
            ]
        );

        return Command::SUCCESS;
    }
}
