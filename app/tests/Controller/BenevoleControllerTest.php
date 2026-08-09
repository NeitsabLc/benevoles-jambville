<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BenevoleControllerTest extends WebTestCase
{
    public function testLePiloteVoitLaListeEtPeutOuvrirUnProfilSansMotDePasse(): void
    {
        $client = self::createClient();
        $utilisateurs = self::getContainer()->get(UtilisateurRepository::class);
        $pilote = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        $benevole = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        self::assertNotNull($pilote);
        self::assertNotNull($benevole);
        if (!$benevole->isActif()) {
            $benevole->basculerActivation();
            self::getContainer()->get(EntityManagerInterface::class)->flush();
        }
        $client->loginUser($pilote);

        $crawler = $client->request('GET', '/administration/benevoles');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Bénévoles');
        self::assertSelectorExists('a[href="/administration/benevoles/importer"]');
        self::assertSelectorExists('a[href="/administration/benevoles/ajouter"]');
        self::assertSelectorTextContains('.corps-table-benevoles', 'Bénévole');

        $client->click($crawler->filter(sprintf('a[href="/administration/benevoles/%s/profil"]', $benevole->getId()))->link());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Camille Bénévole');
        self::assertSelectorNotExists('#mot-de-passe-titre');
        self::assertSelectorExists('button:contains("Enregistrer le profil")');
    }

    public function testLePilotePeutCreerUnCompteUnique(): void
    {
        $client = self::createClient();
        $pilote = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        self::assertNotNull($pilote);
        $client->loginUser($pilote);

        $crawler = $client->request('GET', '/administration/benevoles/ajouter');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Créer un compte');

        $client->submit($crawler->selectButton('Créer et envoyer l’invitation')->form([
            'code_adherent' => 'TEST-CREATION-UNIQUE',
            'nom' => 'Unique',
            'prenom' => 'Alice',
            'email' => 'ALICE.UNIQUE@example.test',
            'telephone' => '06 10 20 30 40',
        ]));
        $client->followRedirect();

        self::assertSelectorTextContains('.alerte-succes', 'a été créé');
        $cree = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'TEST-CREATION-UNIQUE']);
        self::assertNotNull($cree);
        self::assertSame('alice.unique@example.test', $cree->getEmail());
        self::assertTrue($cree->isChangementMotDePasseRequis());

        self::getContainer()->get(Connection::class)->executeStatement('DELETE FROM benevole_jambville.utilisateur WHERE code_adherent = :code', ['code' => 'TEST-CREATION-UNIQUE']);
    }

    public function testLaCreationRefuseUneAdresseEmailDejaUtilisee(): void
    {
        $client = self::createClient();
        $pilote = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        self::assertNotNull($pilote);
        $client->loginUser($pilote);
        $crawler = $client->request('GET', '/administration/benevoles/ajouter');

        $client->submit($crawler->selectButton('Créer et envoyer l’invitation')->form([
            'code_adherent' => 'TEST-DOUBLON-EMAIL',
            'nom' => 'Doublon',
            'prenom' => 'Email',
            'email' => 'PILOTE@JAMBVILLE.TEST',
        ]));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.alerte-erreur', 'utilise déjà ce code adhérent ou cette adresse email');
        self::assertNull(self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'TEST-DOUBLON-EMAIL']));
    }

    public function testUnSalarieNePeutPasOuvrirUnProfilAdministre(): void
    {
        $client = self::createClient();
        $utilisateurs = self::getContainer()->get(UtilisateurRepository::class);
        $salarie = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-ACCUEIL']);
        $benevole = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        self::assertNotNull($salarie);
        self::assertNotNull($benevole);
        $client->loginUser($salarie);

        $client->request('GET', sprintf('/administration/benevoles/%s/profil', $benevole->getId()));

        self::assertResponseStatusCodeSame(403);
    }

    public function testLePilotePeutDesactiverPuisReactiverUnBenevole(): void
    {
        $client = self::createClient();
        $utilisateurs = self::getContainer()->get(UtilisateurRepository::class);
        $pilote = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        $benevole = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        self::assertNotNull($pilote);
        self::assertNotNull($benevole);
        if (!$benevole->isActif()) {
            $benevole->basculerActivation();
            self::getContainer()->get(EntityManagerInterface::class)->flush();
        }
        $client->loginUser($pilote);

        $crawler = $client->request('GET', '/administration/benevoles');
        $formulaire = $crawler->filter(sprintf('form[action="/administration/benevoles/%s/activation"]', $benevole->getId()))->form();
        $client->submit($formulaire);
        $client->followRedirect();

        self::assertSelectorTextContains('.alerte-succes', 'désactivé');
        self::assertSelectorTextContains('.conteneur-ligne-benevole.inactive', 'Inactif');

        $crawler = $client->getCrawler();
        $client->submit($crawler->filter(sprintf('form[action="/administration/benevoles/%s/activation"]', $benevole->getId()))->form());
        $client->followRedirect();

        self::assertSelectorTextContains('.alerte-succes', 'réactivé');

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $benevoleReactive = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        self::assertNotNull($benevoleReactive);
        self::assertTrue($benevoleReactive->isActif());
    }

    public function testLePilotePeutPrevisualiserUnImportCsv(): void
    {
        $client = self::createClient();
        $pilote = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        self::assertNotNull($pilote);
        $client->loginUser($pilote);

        $crawler = $client->request('GET', '/administration/benevoles/importer');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Importer des bénévoles');
        self::assertSelectorExists('input[type="file"][accept*=".csv"]');
        self::assertSelectorExists('form[action="/administration/benevoles/importer#previsualisation-import"][data-turbo="false"]');

        $chemin = tempnam(sys_get_temp_dir(), 'benevoles-csv-');
        self::assertNotFalse($chemin);
        file_put_contents($chemin, "code_adherent;nom;prenom;email;telephone;code_fonction;code_structure\nNOUVEAU-1;Martin;Lou;lou@example.test;06 12 34 56 78;BEN;NAT\n");
        $formulaire = $crawler->selectButton('Prévisualiser l’import')->form();
        $formulaire['fichier_csv']->upload($chemin);
        $client->submit($formulaire);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#previsualisation-import');
        self::assertSelectorTextContains('.carte-apercu-import', 'NOUVEAU-1');
        self::assertSelectorTextContains('.statut-creation', 'Création');
    }

    public function testLImportConvertitUnCsvWindows1252EnUtf8(): void
    {
        $client = self::createClient();
        $pilote = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        self::assertNotNull($pilote);
        $client->loginUser($pilote);
        $crawler = $client->request('GET', '/administration/benevoles/importer');

        $csvUtf8 = "code_adherent;nom;prenom;email;telephone;code_fonction;code_structure\nNOUVEAU-2;Le Caër;Bastien;bastien@example.test;;;\n";
        $csvWindows = mb_convert_encoding($csvUtf8, 'Windows-1252', 'UTF-8');
        $chemin = tempnam(sys_get_temp_dir(), 'benevoles-ansi-');
        self::assertNotFalse($chemin);
        file_put_contents($chemin, $csvWindows);
        $formulaire = $crawler->selectButton('Prévisualiser l’import')->form();
        $formulaire['fichier_csv']->upload($chemin);
        $client->submit($formulaire);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.table-apercu-import', 'Le Caër');
        self::assertSelectorNotExists('.table-apercu-import:contains("�")');
    }

    public function testLImportReconnaitLeETrémaMajusculeDUnCsvMacRoman(): void
    {
        $client = self::createClient();
        $pilote = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        self::assertNotNull($pilote);
        $client->loginUser($pilote);
        $crawler = $client->request('GET', '/administration/benevoles/importer');

        $csvUtf8 = "code_adherent;nom;prenom;email;telephone;code_fonction;code_structure\nNOUVEAU-3;LE CAËR;BASTIEN;bastien.mac@example.test;;;\n";
        $csvMacRoman = iconv('UTF-8', 'MACINTOSH', $csvUtf8);
        self::assertNotFalse($csvMacRoman);
        $chemin = tempnam(sys_get_temp_dir(), 'benevoles-mac-');
        self::assertNotFalse($chemin);
        file_put_contents($chemin, $csvMacRoman);
        $formulaire = $crawler->selectButton('Prévisualiser l’import')->form();
        $formulaire['fichier_csv']->upload($chemin);
        $client->submit($formulaire);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.table-apercu-import', 'LE CAËR');
        self::assertSelectorNotExists('.table-apercu-import:contains("CAèR")');
    }

    public function testLePilotePeutValiderLaPrevisualisationEtAppliquerLImport(): void
    {
        $client = self::createClient();
        $pilote = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        self::assertNotNull($pilote);
        $client->loginUser($pilote);
        $crawler = $client->request('GET', '/administration/benevoles/importer');

        $chemin = tempnam(sys_get_temp_dir(), 'benevoles-apply-');
        self::assertNotFalse($chemin);
        file_put_contents($chemin, "code_adherent;nom;prenom;email;telephone;code_fonction;code_structure\nTEST-IMPORT-APPLY;Importé;Zoé;zoe.import@example.test;0612345678;;\n");
        $formulaire = $crawler->selectButton('Prévisualiser l’import')->form();
        $formulaire['fichier_csv']->upload($chemin);
        $crawler = $client->submit($formulaire);
        self::assertSelectorExists('button:contains("Valider et importer")');
        self::assertSelectorTextContains('.dialog-confirmation-import', 'recevront un email contenant leur accès');

        $client->submit($crawler->selectButton('Confirmer l’import')->form());
        $client->followRedirect();

        self::assertSelectorTextContains('.carte-resultat-import', 'Import appliqué');
        self::assertSelectorExists('a[href="/administration/benevoles/importer/liens"][download]');
        $client->request('GET', '/administration/benevoles/importer/liens');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'text/csv; charset=UTF-8');
        self::assertStringContainsString('zoe.import@example.test', (string) $client->getResponse()->getContent());
        $importe = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'TEST-IMPORT-APPLY']);
        self::assertNotNull($importe);
        self::assertSame('BENEVOLE', $importe->getRoleMetier());

        self::getContainer()->get(Connection::class)->executeStatement('DELETE FROM benevole_jambville.utilisateur WHERE code_adherent = :code', ['code' => 'TEST-IMPORT-APPLY']);
    }

    public function testLaPrevisualisationSignaleUnEmailUtiliseParUnAutreCompte(): void
    {
        $client = self::createClient();
        $pilote = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        self::assertNotNull($pilote);
        $client->loginUser($pilote);
        $crawler = $client->request('GET', '/administration/benevoles/importer');

        $chemin = tempnam(sys_get_temp_dir(), 'benevoles-email-');
        self::assertNotFalse($chemin);
        file_put_contents($chemin, "code_adherent;nom;prenom;email;telephone;code_fonction;code_structure\nAUTRE-CODE;Dupont;Alice;pilote@jambville.test;;;\n");
        $formulaire = $crawler->selectButton('Prévisualiser l’import')->form();
        $formulaire['fichier_csv']->upload($chemin);
        $client->submit($formulaire);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.statut-erreur', 'Adresse email déjà utilisée par le code adhérent DEV-PILOTE');
        self::assertSelectorTextContains('.import-bloque', 'Corrigez les erreurs');
        self::assertSelectorNotExists('[data-ouvrir-confirmation-import]');
    }
}
