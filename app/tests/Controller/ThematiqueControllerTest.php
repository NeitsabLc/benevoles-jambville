<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Repository\UtilisateurRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ThematiqueControllerTest extends WebTestCase
{
    public function testLePilotePeutConsulterLaGestionDesThematiques(): void
    {
        $client = self::createClient();
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);
        $client->request('GET', '/administration/thematiques');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Thématiques');
        self::assertSelectorExists('a[href="/administration/thematiques/ajouter"]');
        self::assertSelectorExists('[data-carte-thematique][data-modification-url]');
        self::assertSelectorNotExists('form input[name="date_debut_evenement"]');

        $client->request('GET', '/administration/thematiques/ajouter');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Ajouter une thématique');
        self::assertSelectorExists('input[name="date_debut_evenement"]');
        self::assertSelectorExists('input[name="exclusive_sur_periode"]');
    }

    public function testLeSalarieAccueilNePeutPasGererLesThematiques(): void
    {
        $client = self::createClient();
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-ACCUEIL']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);
        $client->request('GET', '/administration/thematiques');

        self::assertResponseStatusCodeSame(403);

        $client->request('GET', '/administration/thematiques/ajouter');
        self::assertResponseStatusCodeSame(403);
    }

    public function testLeBenevoleNePeutPasGererLesThematiques(): void
    {
        $client = self::createClient();
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);
        $client->request('GET', '/administration/thematiques');
        self::assertResponseStatusCodeSame(403);
    }

    public function testUnePeriodeEvenementielleIncompleteEstRendueCommeErreurDeFormulaire(): void
    {
        $client = self::createClient();
        $pilote = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        self::assertNotNull($pilote);
        $client->loginUser($pilote);

        $crawler = $client->request('GET', '/administration/thematiques/ajouter');
        $client->submit($crawler->selectButton('Ajouter la thématique')->form([
            'nom' => 'Période incomplète de test',
            'date_debut_evenement' => '2094-05-10',
            'date_fin_evenement' => '',
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('.alerte-erreur', 'Renseignez les deux dates');
    }
}
