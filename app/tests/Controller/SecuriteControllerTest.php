<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Repository\UtilisateurRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SecuriteControllerTest extends WebTestCase
{
    public function testLaPageDeConnexionEstAccessible(): void
    {
        $client = self::createClient();
        $client->request('GET', '/connexion');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Ravi de vous revoir');
        self::assertSelectorExists('input[name="email"]');
        self::assertSelectorExists('input[name="mot_de_passe"]');
        self::assertSelectorExists('input[name="_csrf_token"]');
    }

    public function testUnNomDhoteNonAutoriseEstRejete(): void
    {
        $client = self::createClient();
        $client->request('GET', '/connexion', server: ['HTTP_HOST' => 'attaquant.example']);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testLaPageDAccueilNecessiteUneConnexion(): void
    {
        $client = self::createClient();
        $client->request('GET', '/');

        self::assertResponseRedirects('http://localhost/connexion');
    }

    public function testLaDeconnexionSansJetonCsrfEstRefusee(): void
    {
        $client = self::createClient();
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);

        $client->request('POST', '/deconnexion');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testLaDeconnexionAvecJetonCsrfEstAcceptee(): void
    {
        $client = self::createClient();
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);

        $crawler = $client->request('GET', '/');
        $client->submit($crawler->filter('form[action="/deconnexion"]')->form());

        self::assertResponseRedirects('/connexion');
    }

    public function testLaDesactivationRevoqueUneSessionDejaOuverte(): void
    {
        $client = self::createClient();
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-BENEVOLE-2']);
        self::assertNotNull($utilisateur);
        $connexion = self::getContainer()->get(Connection::class);

        $client->loginUser($utilisateur);
        $client->request('GET', '/');
        self::assertResponseIsSuccessful();

        try {
            $connexion->executeStatement(
                'UPDATE benevole_jambville.utilisateur SET actif = FALSE, desactive_le = CURRENT_TIMESTAMP WHERE id = :id',
                ['id' => $utilisateur->getId()],
            );

            $client->request('GET', '/');

            self::assertResponseRedirects('http://localhost/connexion');
        } finally {
            $connexion->executeStatement(
                'UPDATE benevole_jambville.utilisateur SET actif = TRUE, desactive_le = NULL WHERE id = :id',
                ['id' => $utilisateur->getId()],
            );
        }
    }

    public function testUnePageInexistanteRedirigeSelonLaConnexion(): void
    {
        $client = self::createClient();
        $client->request('GET', '/une-page-qui-n-existe-pas');
        self::assertResponseRedirects('/connexion');

        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);
        $client->request('GET', '/toujours-inexistante');
        self::assertResponseRedirects('/');
    }

    public function testUnLienDePremiereConnexionInvalideRedirigeVersLaConnexion(): void
    {
        $client = self::createClient();
        $client->request('GET', '/premiere-connexion/lien-de-demonstration');

        self::assertResponseRedirects('/connexion');
    }

    public function testLActivationEtLesInformationsPratiquesSontDeuxEtapesSeparees(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        self::assertNotNull($utilisateur);
        $ancienMotDePasse = $utilisateur->getPassword();
        $ancienProfil = [
            $utilisateur->getTelephone(),
            $utilisateur->isVegetarien(),
            $utilisateur->hasAllergieOeuf(),
            $utilisateur->hasAllergieArachide(),
            $utilisateur->getRegimeAutre(),
            $utilisateur->getBesoinCouchage(),
        ];
        $token = $utilisateur->preparerActivation(new \DateTimeImmutable('+1 hour'));
        $utilisateur->demanderInformationsAccueil();
        $entityManager->flush();

        try {
            $crawler = $client->request('GET', '/premiere-connexion/'.$token);

            self::assertResponseIsSuccessful();
            self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');
            self::assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('Cache-Control'));
            self::assertSelectorNotExists('input[name="vegetarien"]');
            self::assertSelectorNotExists('textarea[name="besoin_couchage"]');

            $client->submit($crawler->selectButton('Activer mon espace')->form([
                'mot_de_passe' => 'Une phrase secrète suffisamment longue',
                'confirmation' => 'Une phrase secrète suffisamment longue',
            ]));

            self::assertResponseRedirects('/connexion');
            $crawler = $client->followRedirect();
            $client->submit($crawler->selectButton('Se connecter')->form([
                'email' => $utilisateur->getEmail(),
                'mot_de_passe' => 'Une phrase secrète suffisamment longue',
            ]));
            self::assertResponseRedirects('/bienvenue/informations-pratiques');

            $crawler = $client->followRedirect();
            self::assertSelectorTextContains('h1', 'Préparons votre accueil');
            self::assertSelectorExists('input[name="allergie_oeuf"]');
            self::assertSelectorExists('textarea[name="besoin_couchage"]');
            self::assertSelectorTextContains('.note-authentification', 'modifiées à tout moment depuis votre profil');
            $client->submit($crawler->selectButton('Enregistrer et continuer')->form([
                'vegetarien' => true,
                'allergie_oeuf' => true,
                'regime_autre' => 'Sans lactose',
                'besoin_couchage' => 'Lit proche des sanitaires',
            ]));
            self::assertResponseRedirects('/');

            $entityManager->clear();
            $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
            self::assertNotNull($utilisateur);
            self::assertTrue($utilisateur->isVegetarien());
            self::assertTrue($utilisateur->hasAllergieOeuf());
            self::assertFalse($utilisateur->hasAllergieArachide());
            self::assertSame('Sans lactose', $utilisateur->getRegimeAutre());
            self::assertSame('Lit proche des sanitaires', $utilisateur->getBesoinCouchage());
            self::assertTrue(self::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($utilisateur, 'Une phrase secrète suffisamment longue'));
        } finally {
            $entityManager = self::getContainer()->get(EntityManagerInterface::class);
            $entityManager->clear();
            $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
            if (null !== $utilisateur && null !== $ancienMotDePasse) {
                $utilisateur->setPassword($ancienMotDePasse);
                $utilisateur->terminerActivation();
                $utilisateur->terminerInformationsAccueil();
                $utilisateur->modifierProfil(...$ancienProfil);
                $entityManager->flush();
            }
        }
    }
}
