<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class InformationsLegalesControllerTest extends WebTestCase
{
    public function testLesConditionsDUtilisationSontPubliques(): void
    {
        $client = self::createClient();
        $client->request('GET', '/conditions-utilisation');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Conditions d’utilisation');
        self::assertSelectorTextContains('.contenu-page-legale', '30 jours');
        self::assertSelectorTextContains('.contenu-page-legale', 'adapter le self');
        self::assertSelectorTextContains('.contenu-page-legale', 'informations de transport');
    }

    public function testLaPolitiqueDeConfidentialiteEstPublique(): void
    {
        $client = self::createClient();
        $client->request('GET', '/politique-confidentialite');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Politique de confidentialité');
        self::assertSelectorTextContains('.contenu-page-legale', '10 octobre');
        self::assertSelectorTextContains('.contenu-page-legale', 'Historique statistique');
        self::assertSelectorTextContains('.contenu-page-legale', 'heure d’arrivée à Meulan');
        self::assertSelectorTextContains('.contenu-page-legale', 'consentement');
        self::assertSelectorTextContains('.contenu-page-legale', 'chevauche deux campagnes');
        self::assertSelectorTextContains('.contenu-page-legale', 'cinq fichiers de 10 Mio');
        self::assertSelectorExists('a[href="mailto:contact@neitsab.net"]');
    }
}
