<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Repository\UtilisateurRepository;
use App\Util\UuidV7;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UtilisateurRepositoryTest extends KernelTestCase
{
    public function testLaPurgeAnonymiseLesAuteursSansSupprimerLeursDonneesMetier(): void
    {
        self::bootKernel();
        $connexion = self::getContainer()->get(Connection::class);
        $auteur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        self::assertNotNull($auteur);
        $connexion->beginTransaction();

        try {
            $utilisateurId = UuidV7::generate();
            $inscriptionPersonnelleId = UuidV7::generate();
            $inscriptionCompaId = UuidV7::generate();
            $auditId = UuidV7::generate();
            $connexion->executeStatement(<<<'SQL'
                INSERT INTO benevole_jambville.utilisateur
                    (id, code_adherent, nom, prenom, email, role, source_role, actif, desactive_le)
                VALUES (:id, 'TEST-PURGE', 'À purger', 'Compte', 'purge@example.test',
                        'BENEVOLE', 'MANUEL', FALSE, '2000-01-01')
                SQL, ['id' => $utilisateurId]);
            $connexion->executeStatement(<<<'SQL'
                INSERT INTO benevole_jambville.inscription
                    (id, type, utilisateur_id, thematique_id, date_debut, date_fin, type_couchage,
                     nombre_enfants, nombre_vegetariens, nombre_allergie_oeuf, nombre_allergie_arachide,
                     actif, cree_par_id, modifie_par_id)
                SELECT :id, 'INDIVIDUELLE', :utilisateur, id, '2091-01-01', '2091-01-01', 'DUR',
                       0, 0, 0, 0, TRUE, :utilisateur, :utilisateur
                FROM benevole_jambville.thematique ORDER BY ordre_affichage LIMIT 1
                SQL, ['id' => $inscriptionPersonnelleId, 'utilisateur' => $utilisateurId]);
            $connexion->executeStatement(<<<'SQL'
                INSERT INTO benevole_jambville.inscription
                    (id, type, nom_equipe_compa, nombre_personnes, date_debut, date_fin, type_couchage,
                     nombre_enfants, nombre_vegetariens, nombre_allergie_oeuf, nombre_allergie_arachide,
                     actif, cree_par_id, modifie_par_id)
                VALUES (:id, 'COMPAGNON', 'Équipe conservée', 4, '2091-02-01', '2091-02-01', 'TENTE',
                        0, 0, 0, 0, TRUE, :utilisateur, :utilisateur)
                SQL, ['id' => $inscriptionCompaId, 'utilisateur' => $utilisateurId]);
            $connexion->executeStatement(<<<'SQL'
                INSERT INTO benevole_jambville.journee (date_journee, commentaire, modifie_par_id)
                VALUES ('2091-03-01', 'Journée conservée', :utilisateur)
                SQL, ['utilisateur' => $utilisateurId]);
            $connexion->executeStatement(<<<'SQL'
                INSERT INTO benevole_jambville.journal_audit
                    (id, utilisateur_id, action, type_objet, objet_id)
                VALUES (:id, :utilisateur, 'TEST', 'UTILISATEUR', :utilisateur)
                SQL, ['id' => $auditId, 'utilisateur' => $utilisateurId]);

            $nombre = self::getContainer()->get(UtilisateurRepository::class)->purgerDesactivesAvant(new \DateTimeImmutable('2001-01-01'));

            self::assertSame(1, $nombre);
            self::assertSame(0, (int) $connexion->fetchOne('SELECT COUNT(*) FROM benevole_jambville.inscription WHERE id = :id', ['id' => $inscriptionPersonnelleId]));
            self::assertSame(1, (int) $connexion->fetchOne('SELECT COUNT(*) FROM benevole_jambville.inscription WHERE id = :id', ['id' => $inscriptionCompaId]));
            $inscriptionConservee = $connexion->fetchAssociative('SELECT cree_par_id, modifie_par_id FROM benevole_jambville.inscription WHERE id = :id', ['id' => $inscriptionCompaId]);
            self::assertIsArray($inscriptionConservee);
            self::assertNull($inscriptionConservee['cree_par_id']);
            self::assertNull($inscriptionConservee['modifie_par_id']);
            $journeeConservee = $connexion->fetchAssociative("SELECT modifie_par_id FROM benevole_jambville.journee WHERE date_journee = '2091-03-01'");
            self::assertIsArray($journeeConservee);
            self::assertNull($journeeConservee['modifie_par_id']);
            $auditConserve = $connexion->fetchAssociative('SELECT utilisateur_id, objet_id FROM benevole_jambville.journal_audit WHERE id = :id', ['id' => $auditId]);
            self::assertIsArray($auditConserve);
            self::assertNull($auditConserve['utilisateur_id']);
            self::assertNull($auditConserve['objet_id']);
        } finally {
            $connexion->rollBack();
        }
    }
}
