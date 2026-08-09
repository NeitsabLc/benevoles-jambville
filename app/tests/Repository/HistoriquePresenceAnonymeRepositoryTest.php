<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Repository\HistoriquePresenceAnonymeRepository;
use App\Repository\UtilisateurRepository;
use App\Util\UuidV7;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class HistoriquePresenceAnonymeRepositoryTest extends KernelTestCase
{
    public function testUnePresenceSupprimeeNestPasConserveeDansLHistorique(): void
    {
        self::bootKernel();
        $connexion = self::getContainer()->get(Connection::class);
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy([]);
        self::assertNotNull($utilisateur);
        $connexion->beginTransaction();

        try {
            $conservee = UuidV7::generate();
            $supprimee = UuidV7::generate();
            foreach ([[$conservee, 5], [$supprimee, 7]] as [$id, $effectif]) {
                $connexion->executeStatement(<<<'SQL'
                    INSERT INTO benevole_jambville.inscription
                        (id, type, nom_equipe_compa, nombre_personnes, date_debut, date_fin, type_couchage,
                         nombre_enfants, nombre_vegetariens, nombre_allergie_oeuf, nombre_allergie_arachide,
                         actif, cree_par_id, modifie_par_id)
                    VALUES (:id, 'COMPAGNON', 'Équipe test', :effectif, '2099-09-15', '2099-09-15', 'TENTE',
                            0, 0, 0, 0, TRUE, :utilisateur, :utilisateur)
                    SQL, ['id' => $id, 'effectif' => $effectif, 'utilisateur' => $utilisateur->getId()]);
            }
            $connexion->executeStatement('UPDATE benevole_jambville.inscription SET actif = FALSE WHERE id = :id', ['id' => $supprimee]);

            $nombre = self::getContainer()->get(HistoriquePresenceAnonymeRepository::class)->archiverEtPurgerCampagne(
                new \DateTimeImmutable('2099-09-01'),
                new \DateTimeImmutable('2100-08-31'),
            );

            self::assertSame(2, $nombre);
            self::assertSame(5, (int) $connexion->fetchOne(<<<'SQL'
                SELECT nombre_compagnons
                FROM benevole_jambville.historique_presence_anonyme
                WHERE date_journee = '2099-09-15' AND thematique IS NULL
                SQL));
        } finally {
            $connexion->rollBack();
        }
    }
}
