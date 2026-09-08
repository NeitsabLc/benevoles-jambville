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
        [$debut, $fin] = self::calculerCampagneEligible(new \DateTimeImmutable('today'));
        $nombre = $this->historique->archiverEtPurgerCampagne($debut, $fin);

        if (null === $nombre) {
            $output->writeln(sprintf(
                'La campagne du %s au %s a déjà été purgée : aucune action nécessaire.',
                $debut->format('d/m/Y'),
                $fin->format('d/m/Y'),
            ));

            return Command::SUCCESS;
        }

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

    /** @return array{\DateTimeImmutable, \DateTimeImmutable} */
    public static function calculerCampagneEligible(\DateTimeImmutable $dateReference): array
    {
        $annee = (int) $dateReference->format('Y');
        $datePurgeAnnuelle = new \DateTimeImmutable(sprintf('%d-10-10', $annee));
        $anneeFinCampagne = $dateReference >= $datePurgeAnnuelle ? $annee : $annee - 1;

        return [
            new \DateTimeImmutable(sprintf('%d-09-01', $anneeFinCampagne - 1)),
            new \DateTimeImmutable(sprintf('%d-08-31', $anneeFinCampagne)),
        ];
    }
}
