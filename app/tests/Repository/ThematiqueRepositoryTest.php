<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Repository\ThematiqueRepository;
use App\Util\UuidV7;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ThematiqueRepositoryTest extends KernelTestCase
{
    public function testSeulesLesThematiquesTermineesDepuisPlusDeVingtQuatreHeuresSontDesactivees(): void
    {
        self::bootKernel();
        $connexion = self::getContainer()->get(Connection::class);
        $repository = self::getContainer()->get(ThematiqueRepository::class);
        $expiree = UuidV7::generate();
        $encoreDisponible = UuidV7::generate();

        $connexion->executeStatement(<<<'SQL'
            INSERT INTO benevole_jambville.thematique
                (id, nom, date_debut_evenement, date_fin_evenement)
            VALUES
                (:expiree, 'Événement expiré test', '2030-10-01', '2030-10-08'),
                (:disponible, 'Événement disponible test', '2030-10-01', '2030-10-09')
            SQL, ['expiree' => $expiree, 'disponible' => $encoreDisponible]);

        try {
            self::assertSame(1, $repository->desactiverExpirees(new \DateTimeImmutable('2030-10-10')));
            self::assertFalse((bool) $connexion->fetchOne(
                'SELECT actif FROM benevole_jambville.thematique WHERE id = :id',
                ['id' => $expiree],
            ));
            self::assertTrue((bool) $connexion->fetchOne(
                'SELECT actif FROM benevole_jambville.thematique WHERE id = :id',
                ['id' => $encoreDisponible],
            ));
        } finally {
            $connexion->executeStatement(
                'DELETE FROM benevole_jambville.thematique WHERE id IN (:expiree, :disponible)',
                ['expiree' => $expiree, 'disponible' => $encoreDisponible],
            );
        }
    }
}
