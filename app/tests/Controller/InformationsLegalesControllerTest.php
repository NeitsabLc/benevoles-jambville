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
    }

    public function testLaPolitiqueDeConfidentialiteEstPublique(): void
    {
        $client = self::createClient();
        $client->request('GET', '/politique-confidentialite');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Politique de confidentialité');
        self::assertSelectorTextContains('.contenu-page-legale', '10 octobre');
        self::assertSelectorTextContains('.contenu-page-legale', 'Historique statistique');
        self::assertSelectorExists('a[href="mailto:contact@neitsab.net"]');
    }
}
