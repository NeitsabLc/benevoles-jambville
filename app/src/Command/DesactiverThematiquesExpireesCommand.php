<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ThematiqueRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:thematiques:desactiver-expirees',
    description: 'Désactive les thématiques événementielles terminées depuis plus de 24 heures.',
)]
final class DesactiverThematiquesExpireesCommand extends Command
{
    public function __construct(private readonly ThematiqueRepository $thematiques)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $nombre = $this->thematiques->desactiverExpirees();
        $output->writeln(sprintf('%d thématique%s désactivée%s.', $nombre, $nombre > 1 ? 's' : '', $nombre > 1 ? 's' : ''));

        return Command::SUCCESS;
    }
}
