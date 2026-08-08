<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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

    public function testLaPageDAccueilNecessiteUneConnexion(): void
    {
        $client = self::createClient();
        $client->request('GET', '/');

        self::assertResponseRedirects('http://localhost/connexion');
    }
}
