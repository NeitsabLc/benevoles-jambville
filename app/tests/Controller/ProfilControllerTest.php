<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ProfilControllerTest extends WebTestCase
{
    public function testEquipePiloteVoitEtPeutModifierLeRoleDUnBenevole(): void
    {
        $client = self::createClient();
        $utilisateurs = self::getContainer()->get(UtilisateurRepository::class);
        $pilote = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        $benevole = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        self::assertNotNull($pilote);
        self::assertNotNull($benevole);
        $roleInitial = $benevole->getRoleMetier();
        $client->loginUser($pilote);

        try {
            $crawler = $client->request('GET', sprintf('/administration/benevoles/%s/profil', $benevole->getId()));

            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('#role-titre', 'Rôle');
            self::assertSelectorExists('select[name="role"] option[value="BENEVOLE"]:contains("Bénévole")');
            self::assertSelectorExists('select[name="role"] option[value="EQUIPE_PILOTE"]:contains("Équipe pilote")');
            self::assertSelectorExists('select[name="role"] option[value="SALARIE_ACCUEIL"]:contains("Salarié")');
            self::assertSelectorExists(sprintf('select[name="role"] option[value="%s"][selected]', $roleInitial));

            $client->submit($crawler->selectButton('Enregistrer le profil')->form([
                'role' => 'SALARIE_ACCUEIL',
            ]));

            self::assertResponseRedirects(sprintf('/administration/benevoles/%s/profil', $benevole->getId()));
            $entityManager = self::getContainer()->get(EntityManagerInterface::class);
            $entityManager->clear();
            $benevoleModifie = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
            self::assertNotNull($benevoleModifie);
            self::assertSame('SALARIE_ACCUEIL', $benevoleModifie->getRoleMetier());
            self::assertSame('MANUEL', $benevoleModifie->getSourceRole());
        } finally {
            $entityManager = self::getContainer()->get(EntityManagerInterface::class);
            $entityManager->clear();
            $benevoleARestaurer = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
            if (null !== $benevoleARestaurer) {
                $benevoleARestaurer->modifierRole($roleInitial);
                $entityManager->flush();
            }
        }
    }

    public function testLeNomDansLaBarreDonneAccesAuProfilComplet(): void
    {
        $client = self::createClient();
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);

        $client->request('GET', '/');
        self::assertSelectorTextContains('a[href="/mon-profil"]', $utilisateur->getNomComplet());

        $client->clickLink($utilisateur->getNomComplet());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mon profil');
        self::assertSelectorTextContains('#alimentation-titre', 'Régime et allergies alimentaires');
        self::assertSelectorTextContains('label[for="regime_autre"]', 'Autre régime ou allergie alimentaire');
        self::assertSelectorNotExists('input[value="DEV-BENEVOLE"]');
        self::assertSelectorExists('input[name="telephone"]');
        self::assertSelectorExists('input[name="telephone"][data-telephone-francais]');
        self::assertSelectorExists('[data-erreur-telephone][hidden]');
        self::assertSelectorExists('input[name="vegetarien"]');
        self::assertSelectorExists('input[name="allergie_oeuf"]');
        self::assertSelectorExists('input[name="allergie_arachide"]');
        self::assertSelectorExists('textarea[name="regime_autre"]');
        self::assertSelectorExists('textarea[name="besoin_couchage"]');
        self::assertSelectorExists('textarea[name="regime_autre"][maxlength="1000"]');
        self::assertSelectorExists('textarea[name="besoin_couchage"][maxlength="1000"]');
        self::assertSelectorNotExists('#equipement-titre');
        self::assertSelectorNotExists('input[name="foulard_remis"]');
        self::assertSelectorNotExists('input[name="tenue_remise"]');
        self::assertSelectorNotExists('select[name="role"]');
        self::assertSelectorExists('input[name="mot_de_passe_actuel"]');
        self::assertSelectorExists('input[name="nouveau_mot_de_passe"][minlength="12"]');
        self::assertSelectorExists('input[name="confirmation_mot_de_passe"]');
    }

    public function testUnBenevoleModifieSesInformationsSansPouvoirModifierLesRemises(): void
    {
        $client = self::createClient();
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);

        $crawler = $client->request('GET', '/mon-profil');
        $jeton = $crawler->filter('.formulaire-profil input[name="_csrf_token"]')->attr('value');
        self::assertNotNull($jeton);
        $client->request('POST', '/mon-profil', [
            '_csrf_token' => $jeton,
            'telephone' => '06 12 34 56 78',
            'vegetarien' => '1',
            'allergie_oeuf' => '1',
            'regime_autre' => 'Test sans lactose',
            'besoin_couchage' => 'Test lit bas',
            'foulard_remis' => '1',
            'tenue_remise' => '1',
        ]);

        self::assertResponseRedirects('/mon-profil');
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        self::assertNotNull($utilisateur);
        self::assertSame('06 12 34 56 78', $utilisateur->getTelephone());
        self::assertTrue($utilisateur->isVegetarien());
        self::assertTrue($utilisateur->hasAllergieOeuf());
        self::assertFalse($utilisateur->isFoulardRemis());
        self::assertFalse($utilisateur->isTenueRemise());

        $utilisateur->modifierProfil(null, false, false, false, null, null);
        self::getContainer()->get(EntityManagerInterface::class)->flush();
    }

    public function testEquipePilotePeutModifierLaRemiseDeSonEquipement(): void
    {
        $client = self::createClient();
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        self::assertNotNull($utilisateur);
        $telephoneInitial = $utilisateur->getTelephone();
        $client->loginUser($utilisateur);

        $crawler = $client->request('GET', '/mon-profil');
        self::assertSelectorNotExists('input[name="foulard_remis"][disabled]');
        $client->submit($crawler->selectButton('Enregistrer mon profil')->form([
            'telephone' => '',
            'foulard_remis' => true,
            'tenue_remise' => true,
        ]));

        self::assertResponseRedirects('/mon-profil');
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        self::assertNotNull($utilisateur);
        self::assertTrue($utilisateur->isFoulardRemis());
        self::assertTrue($utilisateur->isTenueRemise());

        $utilisateur->modifierRemiseEquipement(false, false);
        $utilisateur->modifierProfil(
            $telephoneInitial,
            $utilisateur->isVegetarien(),
            $utilisateur->hasAllergieOeuf(),
            $utilisateur->hasAllergieArachide(),
            $utilisateur->getRegimeAutre(),
            $utilisateur->getBesoinCouchage(),
        );
        self::getContainer()->get(EntityManagerInterface::class)->flush();
    }

    public function testUnUtilisateurPeutModifierSonMotDePasse(): void
    {
        $client = self::createClient();
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-ACCUEIL']);
        self::assertNotNull($utilisateur);
        $ancienHash = $utilisateur->getPassword();
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $utilisateur->setPassword($hasher->hashPassword($utilisateur, 'Mot de passe actuel'));
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        try {
            $client->loginUser($utilisateur);

            $crawler = $client->request('GET', '/mon-profil');
            $client->submit($crawler->selectButton('Enregistrer mon profil')->form([
                'mot_de_passe_actuel' => 'Mot de passe actuel',
                'nouveau_mot_de_passe' => 'Une nouvelle phrase secrète',
                'confirmation_mot_de_passe' => 'Une nouvelle phrase secrète',
            ]));

            self::assertResponseRedirects('/mon-profil');
            $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-ACCUEIL']);
            self::assertNotNull($utilisateur);
            self::assertTrue(self::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($utilisateur, 'Une nouvelle phrase secrète'));
        } finally {
            self::assertNotNull($ancienHash);
            $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-ACCUEIL']);
            if (null !== $utilisateur) {
                $utilisateur->setPassword($ancienHash);
                self::getContainer()->get(EntityManagerInterface::class)->flush();
            }
        }
    }

    public function testUnNumeroDeTelephoneInvalideEstRefuse(): void
    {
        $client = self::createClient();
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        self::assertNotNull($utilisateur);
        $telephoneInitial = $utilisateur->getTelephone();
        $client->loginUser($utilisateur);

        $crawler = $client->request('GET', '/mon-profil');
        $client->submit($crawler->selectButton('Enregistrer mon profil')->form(['telephone' => '1234']));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.alerte-erreur', 'numéro français valide');
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        self::assertNotNull($utilisateur);
        self::assertSame($telephoneInitial, $utilisateur->getTelephone());
    }

    public function testLesTextesLibresTropLongsSontRefusesCoteServeur(): void
    {
        $client = self::createClient();
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        self::assertNotNull($utilisateur);
        $regimeInitial = $utilisateur->getRegimeAutre();
        $client->loginUser($utilisateur);

        $crawler = $client->request('GET', '/mon-profil');
        $jeton = $crawler->filter('input[name="_csrf_token"]')->attr('value');
        self::assertNotNull($jeton);
        $client->request('POST', '/mon-profil', [
            '_csrf_token' => $jeton,
            'regime_autre' => str_repeat('a', 1_001),
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.alerte-erreur', 'limité à 1 000 caractères');
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        self::assertNotNull($utilisateur);
        self::assertSame($regimeInitial, $utilisateur->getRegimeAutre());
    }
}
