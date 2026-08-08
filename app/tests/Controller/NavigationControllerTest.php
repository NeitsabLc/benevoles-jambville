<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Repository\UtilisateurRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class NavigationControllerTest extends WebTestCase
{
    public function testLeBenevoleConserveLaBarreSansMenuLateral(): void
    {
        $client = self::createClient();
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.barre-application');
        self::assertSelectorNotExists('.navigation-laterale');
    }

    public function testLePiloteDisposeDeTousLesLiens(): void
    {
        $client = self::createClient();
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.navigation-laterale');
        self::assertSelectorExists('button[data-menu-mobile][aria-controls="navigation-principale"]');
        self::assertSelectorExists('a[href="/synthese"]');
        self::assertSelectorExists('a[href="/administration/calendrier"]');
        self::assertSelectorExists('a[href="/administration/thematiques"]');
        self::assertSelectorExists('a[href="/administration/benevoles"]');
    }

    public function testLeSalarieAccueilNaPasLesLiensReservesAuPilote(): void
    {
        $client = self::createClient();
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-ACCUEIL']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.navigation-laterale');
        self::assertSelectorExists('button[data-menu-mobile][aria-controls="navigation-principale"]');
        self::assertSelectorExists('a[href="/synthese"]');
        self::assertSelectorExists('a[href="/administration/calendrier"]');
        self::assertSelectorExists('a[href="/administration/thematiques"]');
        self::assertSelectorNotExists('a[href="/administration/benevoles"]');

        $client->request('GET', '/administration/benevoles');
        self::assertResponseStatusCodeSame(403);
    }

    public function testLeBenevoleNePeutPasOuvrirLesPagesPrivees(): void
    {
        $client = self::createClient();
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);

        $client->request('GET', '/synthese');
        self::assertResponseStatusCodeSame(403);

        $client->request('GET', '/administration/calendrier');
        self::assertResponseStatusCodeSame(403);
    }
}
