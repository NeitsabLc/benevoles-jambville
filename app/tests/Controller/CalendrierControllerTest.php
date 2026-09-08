<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Repository\UtilisateurRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CalendrierControllerTest extends WebTestCase
{
    public function testUnCommentaireEstAppliqueEnModeCompleterPuisRemplacer(): void
    {
        $client = self::createClient();
        $pilote = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        self::assertNotNull($pilote);
        $connexion = self::getContainer()->get(Connection::class);
        $connexion->executeStatement(
            "DELETE FROM benevole_jambville.journee WHERE date_journee BETWEEN '2093-04-10' AND '2093-04-12'",
        );
        $client->loginUser($pilote);

        try {
            $crawler = $client->request('GET', '/administration/calendrier');
            $client->submit($crawler->selectButton('Appliquer le commentaire')->form([
                'commentaire' => 'Commentaire initial',
                'date_debut' => '2093-04-10',
                'date_fin' => '2093-04-12',
                'mode' => 'completer',
            ]));
            self::assertResponseRedirects('/administration/calendrier');
            self::assertSame(3, (int) $connexion->fetchOne(
                "SELECT COUNT(*) FROM benevole_jambville.journee WHERE date_journee BETWEEN '2093-04-10' AND '2093-04-12' AND commentaire = 'Commentaire initial'",
            ));

            $crawler = $client->request('GET', '/administration/calendrier');
            $client->submit($crawler->selectButton('Appliquer le commentaire')->form([
                'commentaire' => 'Ne doit pas remplacer',
                'date_debut' => '2093-04-10',
                'date_fin' => '2093-04-12',
                'mode' => 'completer',
            ]));
            self::assertSame(3, (int) $connexion->fetchOne(
                "SELECT COUNT(*) FROM benevole_jambville.journee WHERE date_journee BETWEEN '2093-04-10' AND '2093-04-12' AND commentaire = 'Commentaire initial'",
            ));

            $crawler = $client->request('GET', '/administration/calendrier');
            $client->submit($crawler->selectButton('Appliquer le commentaire')->form([
                'commentaire' => 'Commentaire remplacé',
                'date_debut' => '2093-04-10',
                'date_fin' => '2093-04-12',
                'mode' => 'remplacer',
            ]));
            self::assertSame(3, (int) $connexion->fetchOne(
                "SELECT COUNT(*) FROM benevole_jambville.journee WHERE date_journee BETWEEN '2093-04-10' AND '2093-04-12' AND commentaire = 'Commentaire remplacé'",
            ));
        } finally {
            $connexion->executeStatement(
                "DELETE FROM benevole_jambville.journee WHERE date_journee BETWEEN '2093-04-10' AND '2093-04-12'",
            );
        }
    }

    public function testUneEcritureSansJetonCsrfEstRefusee(): void
    {
        $client = self::createClient();
        $pilote = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        self::assertNotNull($pilote);
        $client->loginUser($pilote);

        $client->request('POST', '/administration/calendrier', [
            'action' => 'commentaire',
            'commentaire' => 'Ne doit pas être enregistré',
            'date_debut' => '2093-05-10',
            'date_fin' => '2093-05-10',
            'mode' => 'remplacer',
        ]);

        self::assertResponseRedirects('/administration/calendrier');
        self::assertSame(0, (int) self::getContainer()->get(Connection::class)->fetchOne(
            "SELECT COUNT(*) FROM benevole_jambville.journee WHERE date_journee = '2093-05-10'",
        ));
    }
}
