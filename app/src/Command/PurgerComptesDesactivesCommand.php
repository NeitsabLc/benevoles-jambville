<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UtilisateurRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:comptes:purger-desactives',
    description: 'Supprime les comptes désactivés depuis au moins 30 jours et toutes leurs données associées.',
)]
final class PurgerComptesDesactivesCommand extends Command
{
    public function __construct(private readonly UtilisateurRepository $utilisateurs)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $nombre = $this->utilisateurs->purgerDesactivesAvant(new \DateTimeImmutable('-30 days'));
        $output->writeln(sprintf('%d compte%s définitivement supprimé%s.', $nombre, $nombre > 1 ? 's' : '', $nombre > 1 ? 's' : ''));

        return Command::SUCCESS;
    }
}
