<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Inscription;
use App\Repository\ThematiqueRepository;
use App\Repository\UtilisateurRepository;
use App\Service\RoomingService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RoomingControllerTest extends WebTestCase
{
    public function testLeRoomingAfficheLesQuatreChambresEtCompteLeParentAvecSesEnfants(): void
    {
        $client = self::createClient();
        $utilisateurs = self::getContainer()->get(UtilisateurRepository::class);
        $pilote = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        $benevole = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        $thematique = self::getContainer()->get(ThematiqueRepository::class)->findOneBy(['nom' => 'Accueil']);
        self::assertNotNull($pilote);
        self::assertNotNull($benevole);
        self::assertNotNull($thematique);

        $inscription = Inscription::individuelle(
            $benevole,
            $thematique,
            new \DateTimeImmutable('2097-04-12'),
            new \DateTimeImmutable('2097-04-14'),
            'DUR',
            2,
            null,
        );
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($inscription);
        $entityManager->flush();
        $client->loginUser($pilote);

        $crawler = $client->request('GET', '/rooming?debut=2097-04-12&fin=2097-04-14');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.tableau-planning-rooming', 'Blondin');
        self::assertSelectorTextContains('.tableau-planning-rooming', 'Sizaine');
        self::assertSelectorTextContains('.tableau-planning-rooming', 'Patrouille');
        self::assertSelectorTextContains('.tableau-planning-rooming', 'Colibri');
        self::assertSelectorTextContains('.tableau-planning-rooming tbody tr:nth-child(2) th', '3 lits');
        self::assertSelectorTextContains('.tableau-planning-rooming tbody tr:nth-child(3) th', '6 lits');
        self::assertSelectorTextContains('.tableau-planning-rooming tbody tr:nth-child(4) th', '6 lits');
        self::assertSelectorTextContains('.tableau-planning-rooming tbody tr:nth-child(5) th', '1 lit');
        self::assertSelectorTextContains('.tableau-planning-rooming thead', 'vendredi 12 avril');
        self::assertSelectorCount(3, '.ligne-sans-chambre td li');

        $formulaire = $crawler->filterXPath(
            '//form[input[@name="date_nuit" and @value="2097-04-13"] and input[@name="chambre" and @value="BLONDIN"]]',
        )->first();
        $form = $formulaire->form([
            'inscription' => $inscription->getId(),
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/rooming?debut=2097-04-12&fin=2097-04-14');
        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('.alerte-succes', 'toute la période');
        self::assertSelectorCount(3, '.tableau-planning-rooming tbody tr:nth-child(2) .occupants-rooming li');
        self::assertSelectorTextContains('.tableau-planning-rooming tbody tr:nth-child(2)', 'Camille Bénévole');

        $connexion = self::getContainer()->get(Connection::class);
        self::assertSame(3, (int) $connexion->fetchOne(
            'SELECT nombre_places FROM benevole_jambville.affectation_rooming WHERE inscription_id = :id AND chambre = :chambre',
            ['id' => $inscription->getId(), 'chambre' => 'BLONDIN'],
        ));
        self::assertSame(3, (int) $connexion->fetchOne(
            'SELECT COUNT(*) FROM benevole_jambville.affectation_rooming WHERE inscription_id = :id',
            ['id' => $inscription->getId()],
        ));

        $boutonRetrait = $crawler->filterXPath(sprintf(
            '//form[input[@name="date_nuit" and @value="2097-04-13"] and input[@name="inscription" and @value="%s"]]//button[@name="portee" and @value="nuit"]',
            $inscription->getId(),
        ))->first();
        $client->submit($boutonRetrait->form());
        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('.alerte-succes', 'cette nuit');
        self::assertSelectorTextContains('.ligne-sans-chambre td:nth-child(3)', 'Camille Bénévole');
        self::assertSame(2, (int) $connexion->fetchOne(
            'SELECT COUNT(*) FROM benevole_jambville.affectation_rooming WHERE inscription_id = :id',
            ['id' => $inscription->getId()],
        ));

        $connexion->executeStatement(
            'DELETE FROM benevole_jambville.inscription WHERE id = :id',
            ['id' => $inscription->getId()],
        );
    }

    public function testUneChambreNePeutPasDepasserSaCapacite(): void
    {
        $client = self::createClient();
        $utilisateurs = self::getContainer()->get(UtilisateurRepository::class);
        $pilote = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        $benevole = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        $occupante = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-BENEVOLE-2']);
        $thematique = self::getContainer()->get(ThematiqueRepository::class)->findOneBy(['nom' => 'Accueil']);
        self::assertNotNull($pilote);
        self::assertNotNull($benevole);
        self::assertNotNull($occupante);
        self::assertNotNull($thematique);

        $occupation = Inscription::individuelle($occupante, $thematique, new \DateTimeImmutable('2097-04-14'), new \DateTimeImmutable('2097-04-14'), 'DUR', 2, null);
        $inscription = Inscription::individuelle($benevole, $thematique, new \DateTimeImmutable('2097-04-13'), new \DateTimeImmutable('2097-04-15'), 'DUR', 0, null);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($occupation);
        $entityManager->persist($inscription);
        $entityManager->flush();
        self::getContainer()->get(RoomingService::class)->affecter($occupation, 'BLONDIN', $pilote);
        $client->loginUser($pilote);
        $crawler = $client->request('GET', '/rooming?debut=2097-04-13&fin=2097-04-15');

        $formulaire = $crawler->filterXPath(
            '//form[input[@name="date_nuit" and @value="2097-04-13"] and input[@name="chambre" and @value="BLONDIN"]]',
        )->first();
        $client->submit($formulaire->form([
            'inscription' => $inscription->getId(),
        ]));
        $client->followRedirect();

        self::assertSelectorTextContains('.alerte-erreur', 'Blondin');
        self::assertSelectorTextContains('.alerte-erreur', '14/04/2097');
        self::assertSelectorTextContains('.alerte-erreur', 'portée demandée');
        self::assertSame(0, (int) self::getContainer()->get(Connection::class)->fetchOne(
            'SELECT COUNT(*) FROM benevole_jambville.affectation_rooming WHERE inscription_id = :id',
            ['id' => $inscription->getId()],
        ));

        self::getContainer()->get(Connection::class)->executeStatement(
            'DELETE FROM benevole_jambville.inscription WHERE id IN (:inscription, :occupation)',
            [
                'inscription' => $inscription->getId(),
                'occupation' => $occupation->getId(),
            ],
        );
    }
}
