<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Inscription;
use App\Repository\ThematiqueRepository;
use App\Repository\UtilisateurRepository;
use App\Service\RoomingService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RoomingControllerTest extends WebTestCase
{
    public function testLeRoomingAfficheLesQuatreChambresEtCompteLeParentAvecSesEnfants(): void
    {
        $client = self::createClient();
        $utilisateurs = self::getContainer()->get(UtilisateurRepository::class);
        $pilote = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        $benevole = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        $thematique = self::getContainer()->get(ThematiqueRepository::class)->findOneBy(['nom' => 'Accueil']);
        self::assertNotNull($pilote);
        self::assertNotNull($benevole);
        self::assertNotNull($thematique);

        $inscription = Inscription::individuelle(
            $benevole,
            $thematique,
            new \DateTimeImmutable('2097-04-12'),
            new \DateTimeImmutable('2097-04-14'),
            'DUR',
            2,
            null,
        );
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($inscription);
        $entityManager->flush();
        $client->loginUser($pilote);

        $crawler = $client->request('GET', '/rooming?debut=2097-04-12&fin=2097-04-14');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.tableau-planning-rooming', 'Blondin');
        self::assertSelectorTextContains('.tableau-planning-rooming', 'Sizaine');
        self::assertSelectorTextContains('.tableau-planning-rooming', 'Patrouille');
        self::assertSelectorTextContains('.tableau-planning-rooming', 'Colibri');
        self::assertSelectorTextContains('.tableau-planning-rooming', 'Logement bénévole');
        self::assertSelectorTextContains('.tableau-planning-rooming tbody tr:nth-child(2) th', '3 lits');
        self::assertSelectorTextContains('.tableau-planning-rooming tbody tr:nth-child(3) th', '6 lits');
        self::assertSelectorTextContains('.tableau-planning-rooming tbody tr:nth-child(4) th', '6 lits');
        self::assertSelectorTextContains('.tableau-planning-rooming tbody tr:nth-child(5) th', '1 lit');
        self::assertSelectorTextContains('.tableau-planning-rooming thead', 'vendredi 12 avril');
        self::assertSelectorCount(3, '.ligne-sans-chambre td li');

        $formulaire = $crawler->filterXPath(
            '//form[input[@name="date_nuit" and @value="2097-04-13"] and input[@name="chambre" and @value="BLONDIN"]]',
        )->first();
        $form = $formulaire->form([
            'inscription' => $inscription->getId(),
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/rooming?debut=2097-04-12&fin=2097-04-14');
        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('.alerte-succes', 'toute la période');
        self::assertSelectorCount(3, '.tableau-planning-rooming tbody tr:nth-child(2) .occupants-rooming li');
        self::assertSelectorTextContains('.tableau-planning-rooming tbody tr:nth-child(2)', 'Camille Bénévole');

        $connexion = self::getContainer()->get(Connection::class);
        self::assertSame(3, (int) $connexion->fetchOne(
            'SELECT nombre_places FROM benevole_jambville.affectation_rooming WHERE inscription_id = :id AND chambre = :chambre',
            ['id' => $inscription->getId(), 'chambre' => 'BLONDIN'],
        ));
        self::assertSame(3, (int) $connexion->fetchOne(
            'SELECT COUNT(*) FROM benevole_jambville.affectation_rooming WHERE inscription_id = :id',
            ['id' => $inscription->getId()],
        ));

        $boutonRetrait = $crawler->filterXPath(sprintf(
            '//form[input[@name="date_nuit" and @value="2097-04-13"] and input[@name="inscription" and @value="%s"]]//button[@name="portee" and @value="nuit"]',
            $inscription->getId(),
        ))->first();
        $client->submit($boutonRetrait->form());
        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('.alerte-succes', 'cette nuit');
        self::assertSelectorTextContains('.ligne-sans-chambre td:nth-child(3)', 'Camille Bénévole');
        self::assertSame(2, (int) $connexion->fetchOne(
            'SELECT COUNT(*) FROM benevole_jambville.affectation_rooming WHERE inscription_id = :id',
            ['id' => $inscription->getId()],
        ));

        $connexion->executeStatement(
            'DELETE FROM benevole_jambville.inscription WHERE id = :id',
            ['id' => $inscription->getId()],
        );
    }

    public function testUneChambreNePeutPasDepasserSaCapacite(): void
    {
        $client = self::createClient();
        $utilisateurs = self::getContainer()->get(UtilisateurRepository::class);
        $pilote = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        $benevole = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        $occupante = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-BENEVOLE-2']);
        $thematique = self::getContainer()->get(ThematiqueRepository::class)->findOneBy(['nom' => 'Accueil']);
        self::assertNotNull($pilote);
        self::assertNotNull($benevole);
        self::assertNotNull($occupante);
        self::assertNotNull($thematique);

        $occupation = Inscription::individuelle($occupante, $thematique, new \DateTimeImmutable('2097-04-14'), new \DateTimeImmutable('2097-04-15'), 'DUR', 2, null);
        $inscription = Inscription::individuelle($benevole, $thematique, new \DateTimeImmutable('2097-04-13'), new \DateTimeImmutable('2097-04-15'), 'DUR', 0, null);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($occupation);
        $entityManager->persist($inscription);
        $entityManager->flush();
        self::getContainer()->get(RoomingService::class)->affecter($occupation, 'BLONDIN', $pilote);
        $client->loginUser($pilote);
        $crawler = $client->request('GET', '/rooming?debut=2097-04-13&fin=2097-04-15');

        $formulaire = $crawler->filterXPath(
            '//form[input[@name="date_nuit" and @value="2097-04-13"] and input[@name="chambre" and @value="BLONDIN"]]',
        )->first();
        $client->submit($formulaire->form([
            'inscription' => $inscription->getId(),
        ]));
        $client->followRedirect();

        self::assertSelectorTextContains('.alerte-erreur', 'Blondin');
        self::assertSelectorTextContains('.alerte-erreur', '14/04/2097');
        self::assertSelectorTextContains('.alerte-erreur', 'portée demandée');
        self::assertSame(0, (int) self::getContainer()->get(Connection::class)->fetchOne(
            'SELECT COUNT(*) FROM benevole_jambville.affectation_rooming WHERE inscription_id = :id',
            ['id' => $inscription->getId()],
        ));

        self::getContainer()->get(Connection::class)->executeStatement(
            'DELETE FROM benevole_jambville.inscription WHERE id IN (:inscription, :occupation)',
            [
                'inscription' => $inscription->getId(),
                'occupation' => $occupation->getId(),
            ],
        );
    }

    public function testUnePresenceLimiteeAUneJourneeNestPasProposeeDansLeRooming(): void
    {
        $client = self::createClient();
        $utilisateurs = self::getContainer()->get(UtilisateurRepository::class);
        $pilote = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        $benevole = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-BENEVOLE-2']);
        $thematique = self::getContainer()->get(ThematiqueRepository::class)->findOneBy(['nom' => 'Accueil']);
        self::assertNotNull($pilote);
        self::assertNotNull($benevole);
        self::assertNotNull($thematique);

        $inscription = Inscription::individuelle(
            $benevole,
            $thematique,
            new \DateTimeImmutable('2097-08-18'),
            new \DateTimeImmutable('2097-08-18'),
            'DUR',
            0,
            null,
        );
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($inscription);
        $entityManager->flush();
        $client->loginUser($pilote);

        try {
            $client->request('GET', '/rooming?debut=2097-08-18&fin=2097-08-18');

            self::assertResponseIsSuccessful();
            self::assertSelectorTextNotContains('.tableau-planning-rooming', $benevole->getNomComplet());
            self::assertSelectorNotExists(sprintf('option[value="%s"]', $inscription->getId()));
        } finally {
            self::getContainer()->get(Connection::class)->executeStatement(
                'DELETE FROM benevole_jambville.inscription WHERE id = :id',
                ['id' => $inscription->getId()],
            );
        }
    }

    public function testUneNouvelleChambrePeutEtreCreeeDansUnBatiment(): void
    {
        $client = self::createClient();
        $pilote = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        self::assertNotNull($pilote);
        $connexion = self::getContainer()->get(Connection::class);
        $connexion->executeStatement(
            'DELETE FROM benevole_jambville.chambre_rooming WHERE nom = :nom AND batiment = :batiment',
            ['nom' => 'Suite E2E', 'batiment' => 'Château'],
        );
        $client->loginUser($pilote);

        try {
            $crawler = $client->request('GET', '/administration/chambres');
            $formulaire = $crawler->filterXPath('//form[input[@name="action" and @value="creer_chambre"]]')->first();
            $client->submit($formulaire->form([
                'nom' => 'Suite E2E',
                'batiment' => 'Château',
                'capacite' => 8,
            ]));
            $crawler = $client->followRedirect();

            self::assertSelectorTextContains('.alerte-succes', 'chambre a été créée');
            self::assertSelectorTextContains('.grille-administration-chambres', 'Suite E2E');
            self::assertSelectorTextContains('.grille-administration-chambres', 'Château · 8 lits');
            $chambre = $connexion->fetchAssociative(
                'SELECT code, capacite, batiment FROM benevole_jambville.chambre_rooming WHERE nom = :nom',
                ['nom' => 'Suite E2E'],
            );
            self::assertIsArray($chambre);
            self::assertSame(8, (int) $chambre['capacite']);
            self::assertSame('Château', $chambre['batiment']);
            self::assertSame(1, (int) $connexion->fetchOne(
                <<<'SQL'
                    SELECT COUNT(*)
                    FROM benevole_jambville.configuration_chambre_rooming
                    WHERE chambre = :chambre AND disponible_permanence = TRUE
                    SQL,
                ['chambre' => $chambre['code']],
            ));

            $client->request('GET', '/rooming?debut=2098-06-01&fin=2098-06-01');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('.tableau-planning-rooming', 'Suite E2E');
            self::assertSelectorTextContains('.tableau-planning-rooming', 'Château');

            $crawler = $client->request('GET', '/administration/chambres');
            $formulaire = $crawler->filterXPath('//form[input[@name="action" and @value="creer_chambre"]]')->first();
            $client->submit($formulaire->form([
                'nom' => 'suite e2e',
                'batiment' => 'Château',
                'capacite' => 2,
            ]));
            $client->followRedirect();
            self::assertSelectorTextContains('.alerte-erreur', 'existe déjà dans ce bâtiment');
        } finally {
            $connexion->executeStatement(
                'DELETE FROM benevole_jambville.chambre_rooming WHERE nom = :nom AND batiment = :batiment',
                ['nom' => 'Suite E2E', 'batiment' => 'Château'],
            );
        }
    }

    public function testUneChambreTemporaireNestPlanifiableQuePendantSaPeriode(): void
    {
        $client = self::createClient();
        $utilisateurs = self::getContainer()->get(UtilisateurRepository::class);
        $pilote = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        $benevole = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        $thematique = self::getContainer()->get(ThematiqueRepository::class)->findOneBy(['nom' => 'Accueil']);
        self::assertNotNull($pilote);
        self::assertNotNull($benevole);
        self::assertNotNull($thematique);

        $connexion = self::getContainer()->get(Connection::class);
        $connexion->executeStatement("DELETE FROM benevole_jambville.disponibilite_chambre_rooming WHERE chambre = 'PATROUILLE'");
        $connexion->executeStatement("UPDATE benevole_jambville.configuration_chambre_rooming SET disponible_permanence = TRUE WHERE chambre = 'PATROUILLE'");

        $inscription = Inscription::individuelle(
            $benevole,
            $thematique,
            new \DateTimeImmutable('2098-05-10'),
            new \DateTimeImmutable('2098-05-12'),
            'DUR',
            0,
            null,
        );
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($inscription);
        $entityManager->flush();
        $client->loginUser($pilote);

        try {
            $crawler = $client->request('GET', '/administration/chambres');
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('.page-administration-chambres[data-controller="chambres-disponibilite"][data-action="submit->chambres-disponibilite#envoyer"]');
            self::assertSelectorExists('form[data-chambres-repere="mode-patrouille"]');
            self::assertSelectorExists('form[data-chambres-repere="periode-patrouille"]');
            self::assertSelectorTextContains('.carte-administration-chambre:nth-child(3)', 'Patrouille');
            self::assertSelectorTextContains('.carte-administration-chambre:nth-child(3)', '6 lits');

            $ajoutPeriode = $crawler->filterXPath(
                '//form[input[@name="action" and @value="ajouter_periode"] and input[@name="chambre" and @value="PATROUILLE"]]',
            )->first();
            $client->submit($ajoutPeriode->form([
                'date_debut' => '2098-05-11',
                'date_fin' => '2098-05-11',
            ]));
            $crawler = $client->followRedirect();
            self::assertSelectorTextContains('.alerte-succes', 'période de disponibilité');

            $activationPeriodes = $crawler->filterXPath(
                '//form[input[@name="action" and @value="rendre_par_periodes"] and input[@name="chambre" and @value="PATROUILLE"]]',
            )->first();
            $client->submit($activationPeriodes->form());
            $client->followRedirect();
            self::assertSelectorTextContains('.alerte-succes', 'uniquement pendant les périodes');

            $crawler = $client->request('GET', '/rooming?debut=2098-05-10&fin=2098-05-12');
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('.tableau-planning-rooming tbody tr:nth-child(4) td:nth-child(2).chambre-indisponible');
            self::assertSelectorNotExists('.tableau-planning-rooming tbody tr:nth-child(4) td:nth-child(3).chambre-indisponible');
            self::assertSelectorExists('.tableau-planning-rooming tbody tr:nth-child(4) td:nth-child(4).chambre-indisponible');

            $formulaire = $crawler->filterXPath(
                '//form[input[@name="date_nuit" and @value="2098-05-11"] and input[@name="chambre" and @value="PATROUILLE"]]',
            )->first();
            $client->submit($formulaire->form([
                'inscription' => $inscription->getId(),
                'portee' => 'periode',
            ]));
            $crawler = $client->followRedirect();
            self::assertSelectorTextContains('.alerte-erreur', 'pas disponible le 10/05/2098');
            self::assertSame(0, (int) $connexion->fetchOne(
                'SELECT COUNT(*) FROM benevole_jambville.affectation_rooming WHERE inscription_id = :id',
                ['id' => $inscription->getId()],
            ));

            $formulaire = $crawler->filterXPath(
                '//form[input[@name="date_nuit" and @value="2098-05-11"] and input[@name="chambre" and @value="PATROUILLE"]]',
            )->first();
            $client->submit($formulaire->form([
                'inscription' => $inscription->getId(),
                'portee' => 'nuit',
            ]));
            $client->followRedirect();
            self::assertSelectorTextContains('.alerte-succes', 'pour cette nuit');
            self::assertSame('2098-05-11', (string) $connexion->fetchOne(
                'SELECT date_nuit FROM benevole_jambville.affectation_rooming WHERE inscription_id = :id',
                ['id' => $inscription->getId()],
            ));

            $periode = (string) $connexion->fetchOne(
                "SELECT id FROM benevole_jambville.disponibilite_chambre_rooming WHERE chambre = 'PATROUILLE'",
            );
            $crawler = $client->request('GET', '/administration/chambres');
            $suppression = $crawler->filterXPath(sprintf(
                '//form[input[@name="action" and @value="supprimer_periode"] and input[@name="periode" and @value="%s"]]',
                $periode,
            ))->first();
            $client->submit($suppression->form());
            $client->followRedirect();
            self::assertSelectorTextContains('.alerte-erreur', 'une personne est planifiée le 11/05/2098');
            self::assertSame(1, (int) $connexion->fetchOne(
                'SELECT COUNT(*) FROM benevole_jambville.disponibilite_chambre_rooming WHERE id = :id',
                ['id' => $periode],
            ));
        } finally {
            $connexion->executeStatement(
                'DELETE FROM benevole_jambville.inscription WHERE id = :id',
                ['id' => $inscription->getId()],
            );
            $connexion->executeStatement("DELETE FROM benevole_jambville.disponibilite_chambre_rooming WHERE chambre = 'PATROUILLE'");
            $connexion->executeStatement("UPDATE benevole_jambville.configuration_chambre_rooming SET disponible_permanence = TRUE WHERE chambre = 'PATROUILLE'");
        }
    }
}
