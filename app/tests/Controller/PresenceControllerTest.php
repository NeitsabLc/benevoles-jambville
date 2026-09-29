<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Inscription;
use App\Repository\InscriptionRepository;
use App\Repository\ThematiqueRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PresenceControllerTest extends WebTestCase
{
    public function testMaPresenceEstAfficheeAvantLesAutresTrieesParPrenomPuisParNom(): void
    {
        $client = self::createClient();
        $utilisateurs = self::getContainer()->get(UtilisateurRepository::class);
        $benevole = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        $accueil = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-ACCUEIL']);
        $pilote = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        $thematique = self::getContainer()->get(ThematiqueRepository::class)->findOneBy(['nom' => 'Chantier']);
        self::assertNotNull($benevole);
        self::assertNotNull($accueil);
        self::assertNotNull($pilote);
        self::assertNotNull($thematique);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $connexion = self::getContainer()->get(Connection::class);
        $nettoyerInscriptionsDuTest = static function () use ($connexion, $benevole, $accueil, $pilote): void {
            $connexion->executeStatement(
                <<<'SQL'
                    DELETE FROM benevole_jambville.inscription
                    WHERE date_debut = CAST(:debut AS date)
                      AND date_fin = CAST(:fin AS date)
                      AND utilisateur_id IN (:benevole, :accueil, :pilote)
                    SQL,
                [
                    'debut' => '2095-06-15',
                    'fin' => '2095-06-16',
                    'benevole' => $benevole->getId(),
                    'accueil' => $accueil->getId(),
                    'pilote' => $pilote->getId(),
                ],
            );
        };
        $nettoyerInscriptionsDuTest();

        try {
            foreach ([$accueil, $pilote, $benevole] as $utilisateur) {
                $entityManager->persist(Inscription::individuelle(
                    $utilisateur,
                    $thematique,
                    new \DateTimeImmutable('2095-06-15'),
                    new \DateTimeImmutable('2095-06-16'),
                    'DUR',
                    0,
                    null,
                ));
            }
            $entityManager->flush();
            $client->loginUser($pilote);

            $crawler = $client->request('GET', '/?mois=2095-06');

            self::assertResponseIsSuccessful();
            foreach (['2095-06-15', '2095-06-16'] as $date) {
                $noms = $crawler
                    ->filterXPath(sprintf('//article[contains(concat(" ", normalize-space(@class), " "), " jour-calendrier ")][.//time[@datetime="%s"]]//span[contains(concat(" ", normalize-space(@class), " "), " identite-presence ")]/strong', $date))
                    ->each(static fn ($noeud): string => trim($noeud->text()));
                self::assertSame(['Dominique P.', 'Camille B.', 'Sasha A.'], $noms);
            }
        } finally {
            $nettoyerInscriptionsDuTest();
        }
    }

    public function testUneJourneeChargeeEstLimiteeATroisPresencesAvecUnDetailComplet(): void
    {
        $client = self::createClient();
        $pilote = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        self::assertNotNull($pilote);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $inscriptions = [];

        try {
            for ($numero = 1; $numero <= 5; ++$numero) {
                $inscription = Inscription::compagnon(
                    $pilote,
                    'Équipe calendrier '.$numero,
                    $numero,
                    new \DateTimeImmutable('2098-10-14'),
                    new \DateTimeImmutable('2098-10-14'),
                    'TENTE',
                    0,
                    0,
                    0,
                    null,
                );
                $inscriptions[] = $inscription;
                $entityManager->persist($inscription);
            }
            $entityManager->flush();
            $client->loginUser($pilote);

            $crawler = $client->request('GET', '/?mois=2098-10');
            $jour = $crawler->filterXPath('//article[contains(@class, "jour-calendrier")][.//time[@datetime="2098-10-14"]]');

            self::assertResponseIsSuccessful();
            self::assertCount(3, $jour->filter('.presence-apercu .ligne-presence'));
            self::assertStringContainsString('Voir les 2 autres', $jour->filter('.bouton-details-jour-bureau')->text());
            self::assertSelectorCount(5, '#details-jour-2098-10-14 .liste-details-jour .ligne-presence');
            self::assertSelectorTextContains('#details-jour-2098-10-14 .entete-details-jour', '15 personnes · 5 inscriptions');
        } finally {
            foreach ($inscriptions as $inscription) {
                $entityManager->remove($inscription);
            }
            $entityManager->flush();
        }
    }

    public function testLeCalendrierAuthentifieAfficheLesThematiques(): void
    {
        $client = self::createClient();
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Qui sera à Jambville');
        self::assertGreaterThanOrEqual(9, $crawler->filter('select[name="filtre"] option')->count());
        $libellesFiltres = $crawler->filter('select[name="filtre"] option')->each(static fn ($option): string => trim($option->text()));
        foreach (['Toutes les présences', 'Compas', 'Abeille', 'Accueil', 'Au service', 'Audiovisuel', 'Chantier', 'Scout Market', 'Technique infra'] as $libelle) {
            self::assertContains($libelle, $libellesFiltres);
        }
        self::assertSelectorTextContains('select[name="filtre"]', 'Scout Market');
        self::assertSelectorTextContains('.legende-calendrier', 'Ma présence');
        self::assertSelectorTextContains('a[href="/presences/ajouter"]', 'Ajouter ma présence');
        self::assertSelectorExists('dialog[data-dialog-suppression-presence]');
        self::assertSelectorExists('dialog[data-dialog-suppression-presence] button.confirmer-suppression-presence');
    }

    public function testUnBenevoleAccedeAuFormulaireIndividuel(): void
    {
        $client = self::createClient();
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);

        $client->request('GET', '/presences/ajouter');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.page-formulaire-presence > .entete-formulaire-presence');
        self::assertSelectorNotExists('.carte-presence .entete-formulaire-presence');
        self::assertGreaterThanOrEqual(8, $client->getCrawler()->filter('select[name="thematique"] option')->count());
        self::assertSelectorNotExists('[data-mode-button="compa"]');
        self::assertSelectorNotExists('select[data-controller="searchable-select"]');
        self::assertSelectorExists('label[for="nombre_enfants"]');
        self::assertSelectorExists('input#nombre_enfants[name="nombre_enfants"][type="number"][min="0"]:not([disabled])');
        self::assertSelectorExists('input#transport_meulan[name="transport_meulan"][type="checkbox"]');
        self::assertSelectorExists('[data-heure-transport-meulan]:not([hidden])');
        self::assertSelectorExists('input#heure_transport_meulan[name="heure_transport_meulan"][type="time"][disabled]');
    }

    public function testUnBesoinDeTransportDepuisMeulanEstEnregistreAvecSonHeure(): void
    {
        $client = self::createClient();
        $benevole = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        $thematique = self::getContainer()->get(ThematiqueRepository::class)->findOneBy(['nom' => 'Chantier']);
        self::assertNotNull($benevole);
        self::assertNotNull($thematique);
        $client->loginUser($benevole);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        foreach (self::getContainer()->get(InscriptionRepository::class)->findPourCalendrier(new \DateTimeImmutable('2096-08-17'), new \DateTimeImmutable('2096-08-17'), null) as $ancienneInscription) {
            if ($ancienneInscription->getUtilisateur()?->getId() === $benevole->getId()) {
                $entityManager->remove($ancienneInscription);
            }
        }
        $entityManager->flush();

        $crawler = $client->request('GET', '/presences/ajouter');
        $jeton = $crawler->filter('.formulaire-presence input[name="_csrf_token"]')->attr('value');
        self::assertNotNull($jeton);
        $client->request('POST', '/presences/ajouter', [
            '_csrf_token' => $jeton,
            'mode' => 'benevole',
            'thematique' => $thematique->getId(),
            'nombre_enfants' => 0,
            'date_debut' => '2096-08-17',
            'date_fin' => '2096-08-17',
            'type_couchage' => 'DUR',
            'transport_meulan' => '1',
            'heure_transport_meulan' => '09:35',
        ]);

        self::assertResponseRedirects('/?mois=2096-08');
        $inscriptions = self::getContainer()->get(InscriptionRepository::class)->findPourCalendrier(new \DateTimeImmutable('2096-08-17'), new \DateTimeImmutable('2096-08-17'), null);
        $inscription = array_find($inscriptions, static fn ($item) => $item->getUtilisateur()?->getId() === $benevole->getId());
        self::assertNotNull($inscription);
        self::assertSame('09:35', $inscription->getHeureTransportMeulan()?->format('H:i'));

        $client->request('GET', '/presences/'.$inscription->getId().'/modifier');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input#transport_meulan[checked]');
        self::assertSelectorExists('input#heure_transport_meulan[value="09:35"]:not([disabled])');

        $inscriptionGeree = self::getContainer()->get(InscriptionRepository::class)->find($inscription->getId());
        self::assertNotNull($inscriptionGeree);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->remove($inscriptionGeree);
        $entityManager->flush();
    }

    public function testUneHeureEstObligatoireQuandLeTransportDepuisMeulanEstDemande(): void
    {
        $client = self::createClient();
        $benevole = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        $thematique = self::getContainer()->get(ThematiqueRepository::class)->findOneBy(['nom' => 'Chantier']);
        self::assertNotNull($benevole);
        self::assertNotNull($thematique);
        $client->loginUser($benevole);

        $crawler = $client->request('GET', '/presences/ajouter');
        $jeton = $crawler->filter('.formulaire-presence input[name="_csrf_token"]')->attr('value');
        self::assertNotNull($jeton);
        $client->request('POST', '/presences/ajouter', [
            '_csrf_token' => $jeton,
            'mode' => 'benevole',
            'thematique' => $thematique->getId(),
            'nombre_enfants' => 0,
            'date_debut' => '2096-08-18',
            'date_fin' => '2096-08-18',
            'type_couchage' => 'DUR',
            'transport_meulan' => '1',
            'heure_transport_meulan' => '',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.alerte-erreur', 'heure valide pour le transport depuis Meulan');
        self::assertSelectorExists('input#transport_meulan[checked]');
        self::assertSelectorExists('input#heure_transport_meulan[required]:not([disabled])');
    }

    public function testLeFiltreCompaEstSelectionneEtExpliqueUnMoisVide(): void
    {
        $client = self::createClient();
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);

        $client->request('GET', '/?mois=2099-01&filtre=compa');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select[name="filtre"] option[value="compa"][selected]');
        self::assertSelectorTextContains('.etat-vide-filtre', 'Aucune équipe compa');
    }

    public function testUnBenevoleNePeutPasOuvrirLeModeCompa(): void
    {
        $client = self::createClient();
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);

        $client->request('GET', '/presences/ajouter?mode=compa');

        self::assertResponseStatusCodeSame(403);
    }

    public function testLeRoleAccueilNeDisposeQueDuCalendrier(): void
    {
        $client = self::createClient();
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-ACCUEIL']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);

        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href="/presences/ajouter"]');
        self::assertSelectorNotExists('.actions-presence');

        $client->request('GET', '/presences/ajouter');
        self::assertResponseStatusCodeSame(403);
    }

    public function testEquipePiloteAccedeAuModeCompa(): void
    {
        $client = self::createClient();
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);

        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('a[href="/presences/ajouter"]', 'Ajouter une présence');

        $client->request('GET', '/presences/ajouter?mode=compa');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="nom_equipe_compa"]:not([disabled])');
        self::assertSelectorExists('[data-mode-button="compa"].actif');
    }

    public function testEquipePilotePeutRechercherEtInscrireUnAutreBenevole(): void
    {
        $client = self::createClient();
        $utilisateurs = self::getContainer()->get(UtilisateurRepository::class);
        $pilote = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        $benevole = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        $thematique = self::getContainer()->get(ThematiqueRepository::class)->findOneBy(['nom' => 'Chantier']);
        self::assertNotNull($pilote);
        self::assertNotNull($benevole);
        self::assertNotNull($thematique);
        $client->loginUser($pilote);

        $crawler = $client->request('GET', '/presences/ajouter');
        self::assertSelectorExists('select[data-controller="searchable-select"]');
        self::assertSelectorTextContains('[data-mode-button="benevole"]', 'J’inscris un bénévole');
        self::assertSelectorExists('select[name="utilisateur"] option[value="'.$pilote->getId().'"][selected]');
        self::assertSelectorExists('select[name="utilisateur"] option[value="'.$benevole->getId().'"]');
        self::assertSame(
            $benevole->getNomComplet(),
            trim($crawler->filter('select[name="utilisateur"] option[value="'.$benevole->getId().'"]')->text()),
        );
        $formulaire = $crawler->selectButton('Ajouter la présence')->form([
            'utilisateur' => $benevole->getId(),
            'thematique' => $thematique->getId(),
            'nombre_enfants' => 0,
            'date_debut' => '2097-03-20',
            'date_fin' => '2097-03-20',
        ]);
        $client->submit($formulaire);

        self::assertResponseRedirects('/?mois=2097-03');
        $inscriptions = self::getContainer()->get(InscriptionRepository::class)->findPourCalendrier(new \DateTimeImmutable('2097-03-20'), new \DateTimeImmutable('2097-03-20'), null);
        $inscription = array_find($inscriptions, static fn ($item) => $item->getUtilisateur()?->getId() === $benevole->getId());
        self::assertNotNull($inscription);
        self::assertSame('AUCUN', $inscription->getTypeCouchage());

        $client->request('GET', '/presences/'.$inscription->getId().'/modifier');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-champ-couchage][hidden]');
        self::assertSelectorExists('select[name="type_couchage"][disabled]');

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $inscriptionGeree = self::getContainer()->get(InscriptionRepository::class)->find($inscription->getId());
        self::assertNotNull($inscriptionGeree);
        $entityManager->remove($inscriptionGeree);
        $entityManager->flush();
    }

    public function testLesRepasSelectionnesSontEnregistresIndividuellement(): void
    {
        $client = self::createClient();
        $benevole = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        $thematique = self::getContainer()->get(ThematiqueRepository::class)->findOneBy(['nom' => 'Audiovisuel']);
        self::assertNotNull($benevole);
        self::assertNotNull($thematique);
        $client->loginUser($benevole);

        $crawler = $client->request('GET', '/presences/ajouter');
        $jeton = $crawler->filter('.formulaire-presence input[name="_csrf_token"]')->attr('value');
        self::assertNotNull($jeton);
        $client->request('POST', '/presences/ajouter', [
            '_csrf_token' => $jeton,
            'mode' => 'benevole',
            'thematique' => $thematique->getId(),
            'nombre_enfants' => 0,
            'date_debut' => '2096-05-10',
            'date_fin' => '2096-05-11',
            'type_couchage' => 'DUR',
            'repas_configures' => '1',
            'repas' => [
                '2096-05-10' => ['DEJEUNER'],
                '2096-05-11' => ['DINER'],
            ],
        ]);

        self::assertResponseRedirects('/?mois=2096-05');
        $inscriptions = self::getContainer()->get(InscriptionRepository::class)->findPourCalendrier(new \DateTimeImmutable('2096-05-10'), new \DateTimeImmutable('2096-05-11'), null);
        $inscription = array_find($inscriptions, static fn ($item) => $item->getUtilisateur()?->getId() === $benevole->getId());
        self::assertNotNull($inscription);
        self::assertSame(6, $inscription->getNombreRepas());
        self::assertSame(2, $inscription->getNombreRepasSelectionnes());

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->remove($inscription);
        $entityManager->flush();
    }

    public function testEquipePilotePeutCreerUnePresenceCompaEtSesRepas(): void
    {
        $client = self::createClient();
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);

        $crawler = $client->request('GET', '/presences/ajouter?mode=compa');
        $formulaire = $crawler->selectButton('Ajouter la présence')->form([
            'nom_equipe_compa' => 'Compas test PHPUnit',
            'nombre_personnes' => 5,
            'date_debut' => '2040-04-10',
            'date_fin' => '2040-04-11',
            'type_couchage' => 'TENTE',
            'nombre_vegetariens' => 2,
            'nombre_sans_lactose' => 1,
            'nombre_sans_gluten' => 1,
        ]);
        $client->submit($formulaire);

        self::assertResponseRedirects('/?mois=2040-04');
        $inscriptions = self::getContainer()->get(InscriptionRepository::class)->findPourCalendrier(
            new \DateTimeImmutable('2040-04-10'),
            new \DateTimeImmutable('2040-04-11'),
            'compa',
        );
        $inscription = array_find($inscriptions, static fn ($item) => 'Compas test PHPUnit' === $item->getNomEquipeCompa());
        self::assertNotNull($inscription);
        self::assertSame(6, $inscription->getNombreRepas());
        self::assertSame(2, $inscription->getNombreVegetariens());
        self::assertSame(1, $inscription->getNombreSansLactose());
        self::assertSame(1, $inscription->getNombreSansGluten());

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $inscriptionGeree = self::getContainer()->get(InscriptionRepository::class)->find($inscription->getId());
        self::assertNotNull($inscriptionGeree);
        $entityManager->remove($inscriptionGeree);
        $entityManager->flush();
    }

    public function testUnePresenceQuiChevaucheUneThematiqueExclusiveEstRefusee(): void
    {
        $client = self::createClient();
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        $thematiqueOrdinaire = self::getContainer()->get(ThematiqueRepository::class)->findOneBy(['nom' => 'Accueil']);
        self::assertNotNull($utilisateur);
        self::assertNotNull($thematiqueOrdinaire);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $exclusive = new \App\Entity\Thematique('Événement exclusif de test');
        $exclusive->modifier(
            'Événement exclusif de test',
            0,
            new \DateTimeImmutable('2092-06-10'),
            new \DateTimeImmutable('2092-06-12'),
            true,
        );
        $entityManager->persist($exclusive);
        $entityManager->flush();
        $exclusiveId = $exclusive->getId();
        $client->loginUser($utilisateur);

        try {
            $crawler = $client->request('GET', '/presences/ajouter');
            $jeton = $crawler->filter('.formulaire-presence input[name="_csrf_token"]')->attr('value');
            self::assertNotNull($jeton);
            $client->request('POST', '/presences/ajouter', [
                '_csrf_token' => $jeton,
                'mode' => 'benevole',
                'date_debut' => '2092-06-09',
                'date_fin' => '2092-06-11',
                'type_couchage' => 'DUR',
                'thematique' => $thematiqueOrdinaire->getId(),
                'nombre_enfants' => 0,
            ]);

            self::assertResponseStatusCodeSame(422);
            self::assertSelectorTextContains('.alerte-erreur', 'chevauchent la période exclusive');
        } finally {
            self::getContainer()->get(Connection::class)->executeStatement(
                'DELETE FROM benevole_jambville.thematique WHERE id = :id',
                ['id' => $exclusiveId],
            );
        }
    }

    public function testModificationEstReserveeAuProprietaireEtALEquipePilote(): void
    {
        $client = self::createClient();
        $utilisateurs = self::getContainer()->get(UtilisateurRepository::class);
        $proprietaire = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        $salarie = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-ACCUEIL']);
        $pilote = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        $thematique = self::getContainer()->get(ThematiqueRepository::class)->findOneBy(['nom' => 'Accueil']);
        self::assertNotNull($proprietaire);
        self::assertNotNull($salarie);
        self::assertNotNull($pilote);
        self::assertNotNull($thematique);

        $inscription = Inscription::individuelle($proprietaire, $thematique, new \DateTimeImmutable('2098-02-10'), new \DateTimeImmutable('2098-02-10'), 'DUR', 0, null);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($inscription);
        $entityManager->flush();

        $client->loginUser($proprietaire);
        $client->request('GET', '/presences/'.$inscription->getId().'/modifier');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('button.bouton-supprimer-presence[data-suppression-presence]');
        self::assertSelectorExists('dialog[data-dialog-suppression-presence]');

        $client->loginUser($salarie);
        $client->request('GET', '/presences/'.$inscription->getId().'/modifier');
        self::assertResponseStatusCodeSame(403);

        $client->loginUser($pilote);
        $client->request('GET', '/presences/'.$inscription->getId().'/modifier');
        self::assertResponseIsSuccessful();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $inscriptionGeree = self::getContainer()->get(InscriptionRepository::class)->find($inscription->getId());
        self::assertNotNull($inscriptionGeree);
        $entityManager->remove($inscriptionGeree);
        $entityManager->flush();
    }

    public function testModifierUnePeriodeConserveLesRepasExistantsSansDoublon(): void
    {
        self::bootKernel();
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        $thematique = self::getContainer()->get(ThematiqueRepository::class)->findOneBy(['nom' => 'Accueil']);
        self::assertNotNull($utilisateur);
        self::assertNotNull($thematique);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        foreach (self::getContainer()->get(InscriptionRepository::class)->findPourCalendrier(new \DateTimeImmutable('2094-06-12'), new \DateTimeImmutable('2094-06-13'), null) as $ancienneInscription) {
            if ($ancienneInscription->getUtilisateur()?->getId() === $utilisateur->getId()) {
                $entityManager->remove($ancienneInscription);
            }
        }
        $entityManager->flush();

        $inscription = Inscription::individuelle($utilisateur, $thematique, new \DateTimeImmutable('2094-06-12'), new \DateTimeImmutable('2094-06-13'), 'DUR', 0, null);
        $entityManager->persist($inscription);
        $entityManager->flush();

        $inscription->modifierIndividuelle($utilisateur, $thematique, new \DateTimeImmutable('2094-06-12'), new \DateTimeImmutable('2094-06-13'), 'TENTE', 0, 'Modification de test', $utilisateur);
        $entityManager->flush();

        self::assertSame(6, $inscription->getNombreRepas());
        $entityManager->remove($inscription);
        $entityManager->flush();
    }

    public function testUnCommentaireTropLongEstRefuseCoteServeur(): void
    {
        $client = self::createClient();
        $benevole = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        $thematique = self::getContainer()->get(ThematiqueRepository::class)->findOneBy(['nom' => 'Chantier']);
        self::assertNotNull($benevole);
        self::assertNotNull($thematique);
        $client->loginUser($benevole);

        $crawler = $client->request('GET', '/presences/ajouter');
        self::assertSelectorExists('textarea[name="commentaire"][maxlength="1000"]');
        $jeton = $crawler->filter('input[name="_csrf_token"]')->attr('value');
        self::assertNotNull($jeton);
        $client->request('POST', '/presences/ajouter', [
            '_csrf_token' => $jeton,
            'mode' => 'benevole',
            'thematique' => $thematique->getId(),
            'nombre_enfants' => 0,
            'date_debut' => '2099-04-10',
            'date_fin' => '2099-04-10',
            'type_couchage' => 'DUR',
            'commentaire' => str_repeat('a', 1_001),
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.alerte-erreur', 'limité à 1 000 caractères');
    }
}
