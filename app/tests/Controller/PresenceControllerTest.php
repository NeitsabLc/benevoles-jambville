<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Repository\UtilisateurRepository;
use App\Repository\InscriptionRepository;
use App\Repository\ThematiqueRepository;
use App\Entity\Inscription;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PresenceControllerTest extends WebTestCase
{
    public function testLeCalendrierAuthentifieAfficheLesThematiques(): void
    {
        $client = self::createClient();
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Qui sera à Jambville');
        self::assertSelectorCount(9, 'select[name="filtre"] option');
        self::assertSame(
            ['Toutes les présences', 'Compas', 'Abeille', 'Accueil', 'Au service', 'Audiovisuel', 'Chantier', 'Scout Market', 'Technique infra'],
            $crawler->filter('select[name="filtre"] option')->each(static fn ($option): string => trim($option->text())),
        );
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
        self::assertSelectorCount(8, 'select[name="thematique"] option');
        self::assertSelectorNotExists('[data-mode-button="compa"]');
        self::assertSelectorNotExists('select[data-controller="searchable-select"]');
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
            'type_couchage' => 'DUR',
        ]);
        $client->submit($formulaire);

        self::assertResponseRedirects('/?mois=2097-03');
        $inscriptions = self::getContainer()->get(InscriptionRepository::class)->findPourCalendrier(new \DateTimeImmutable('2097-03-20'), new \DateTimeImmutable('2097-03-20'), null);
        $inscription = array_find($inscriptions, static fn ($item) => $item->getUtilisateur()?->getId() === $benevole->getId());
        self::assertNotNull($inscription);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->remove($inscription);
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
            'nombre_allergie_oeuf' => 1,
            'nombre_allergie_arachide' => 1,
        ]);
        $client->submit($formulaire);

        self::assertResponseRedirects('/?mois=2040-04');
        $inscriptions = self::getContainer()->get(InscriptionRepository::class)->findPourCalendrier(
            new \DateTimeImmutable('2040-04-10'),
            new \DateTimeImmutable('2040-04-11'),
            'compa',
        );
        $inscription = array_find($inscriptions, static fn ($item) => $item->getNomEquipeCompa() === 'Compas test PHPUnit');
        self::assertNotNull($inscription);
        self::assertSame(6, $inscription->getNombreRepas());
        self::assertSame(2, $inscription->getNombreVegetariens());
        self::assertSame(1, $inscription->getNombreAllergieOeuf());
        self::assertSame(1, $inscription->getNombreAllergieArachide());

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $inscriptionGeree = self::getContainer()->get(InscriptionRepository::class)->find($inscription->getId());
        self::assertNotNull($inscriptionGeree);
        $entityManager->remove($inscriptionGeree);
        $entityManager->flush();
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
}
