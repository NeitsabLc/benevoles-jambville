<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\HistoriquePresenceAnonymeRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:donnees:purger-campagne-precedente',
    description: 'Conserve les statistiques anonymes puis supprime les inscriptions de la campagne précédente.',
)]
final class PurgerCampagnePrecedenteCommand extends Command
{
    public function __construct(private readonly HistoriquePresenceAnonymeRepository $historique)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $annee = (int) (new \DateTimeImmutable())->format('Y');
        $debut = new \DateTimeImmutable(sprintf('%d-09-01', $annee - 1));
        $fin = new \DateTimeImmutable(sprintf('%d-08-31', $annee));
        $nombre = $this->historique->archiverEtPurgerCampagne($debut, $fin);

        $output->writeln(sprintf(
            '%d inscription%s supprimée%s après conservation des statistiques anonymes du %s au %s.',
            $nombre,
            $nombre > 1 ? 's' : '',
            $nombre > 1 ? 's' : '',
            $debut->format('d/m/Y'),
            $fin->format('d/m/Y'),
        ));

        return Command::SUCCESS;
    }
}
