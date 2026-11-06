<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\RefreshTokenRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Tâche de nuit : la table des jetons grossit sans fin si on ne la nettoie jamais. */
#[AsCommand(
    name: 'app:purge-refresh-tokens',
    description: 'Supprime les jetons de rafraîchissement expirés.',
)]
final class PurgeRefreshTokensCommand extends Command
{
    public function __construct(private readonly RefreshTokenRepository $repository)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $deleted = $this->repository->purgeExpired();

        (new SymfonyStyle($input, $output))->success(
            \sprintf('%d jeton%s expiré%s supprimé%s.', $deleted, $deleted > 1 ? 's' : '', $deleted > 1 ? 's' : '', $deleted > 1 ? 's' : '')
        );

        return Command::SUCCESS;
    }
}
