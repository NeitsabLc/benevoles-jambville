<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Repository\UtilisateurRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ThematiqueControllerTest extends WebTestCase
{
    public function testLeSalariePeutConsulterLaGestionDesThematiques(): void
    {
        $client = self::createClient();
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-ACCUEIL']);
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

    public function testLeBenevoleNePeutPasGererLesThematiques(): void
    {
        $client = self::createClient();
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);
        $client->request('GET', '/administration/thematiques');
        self::assertResponseStatusCodeSame(403);
    }
}
