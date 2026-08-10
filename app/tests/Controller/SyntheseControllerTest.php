<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Inscription;
use App\Repository\ThematiqueRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SyntheseControllerTest extends WebTestCase
{
    public function testLesIdentitesSontTrieesParPrenomPuisParNomDansToutesLesListes(): void
    {
        $client = self::createClient();
        $utilisateurs = self::getContainer()->get(UtilisateurRepository::class);
        $benevole = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        $accueil = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-ACCUEIL']);
        $pilote = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        $thematique = self::getContainer()->get(ThematiqueRepository::class)->findOneBy(['nom' => 'Accueil']);
        self::assertNotNull($benevole);
        self::assertNotNull($accueil);
        self::assertNotNull($pilote);
        self::assertNotNull($thematique);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $inscriptionsExistantes = self::getContainer()->get(\App\Repository\InscriptionRepository::class)->findPourCalendrier(
            new \DateTimeImmutable('2095-07-10'),
            new \DateTimeImmutable('2095-07-10'),
            null,
        );
        foreach ($inscriptionsExistantes as $inscriptionExistante) {
            if (in_array($inscriptionExistante->getUtilisateur()?->getId(), [$benevole->getId(), $accueil->getId(), $pilote->getId()], true)) {
                $entityManager->remove($inscriptionExistante);
            }
        }
        $entityManager->flush();

        $inscriptionsCreees = [];
        foreach ([[$accueil, 'DUR'], [$pilote, 'TENTE'], [$benevole, 'DUR']] as [$utilisateur, $couchage]) {
            $inscription = Inscription::individuelle(
                $utilisateur,
                $thematique,
                new \DateTimeImmutable('2095-07-10'),
                new \DateTimeImmutable('2095-07-10'),
                $couchage,
                0,
                null,
            );
            $inscriptionsCreees[] = $inscription;
            $entityManager->persist($inscription);
        }
        $entityManager->flush();
        $client->loginUser($pilote);

        $crawler = $client->request('GET', '/synthese?debut=2095-07-10&fin=2095-07-10');

        self::assertResponseIsSuccessful();
        $listes = $crawler->filter('.details-synthese > div')->each(
            static fn ($bloc): array => $bloc->filter(':scope > .pastille-presence')->each(
                static fn ($presence): string => trim($presence->text()),
            ),
        );
        self::assertSame(['Camille B.', 'Dominique P.', 'Sasha A.'], $listes[0]);
        self::assertSame(['Camille B.', 'Sasha A.'], $listes[1]);
        self::assertSame(['Dominique P.'], $listes[2]);

        foreach ($inscriptionsCreees as $inscription) {
            $entityManager->remove($inscription);
        }
        $entityManager->flush();
    }

    public function testLaSyntheseCompteRepasCouchagesEtRegimesSansLesAssocierAuxIdentites(): void
    {
        $client = self::createClient();
        $utilisateurs = self::getContainer()->get(UtilisateurRepository::class);
        $pilote = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        $benevole = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-BENEVOLE']);
        $thematique = self::getContainer()->get(ThematiqueRepository::class)->findOneBy(['nom' => 'Accueil']);
        self::assertNotNull($pilote);
        self::assertNotNull($benevole);
        self::assertNotNull($thematique);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        foreach (self::getContainer()->get(\App\Repository\InscriptionRepository::class)->findPourCalendrier(new \DateTimeImmutable('2095-06-12'), new \DateTimeImmutable('2095-06-12'), null) as $ancienneInscription) {
            if ($ancienneInscription->getUtilisateur()?->getId() === $benevole->getId()) {
                $entityManager->remove($ancienneInscription);
            }
        }
        $entityManager->flush();

        $inscription = Inscription::individuelle($benevole, $thematique, new \DateTimeImmutable('2095-06-12'), new \DateTimeImmutable('2095-06-12'), 'DUR', 2, null);
        $inscription->definirRepasSelectionnes(['2095-06-12|DEJEUNER']);
        $entityManager->persist($inscription);
        $entityManager->flush();

        $client->loginUser($pilote);
        $client->request('GET', '/synthese?debut=2095-06-12&fin=2095-06-12');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.jour-synthese', 'Camille B. + 2 enfants');
        self::assertSelectorTextSame('.repas-synthese div:nth-child(2) strong', '3');
        self::assertSelectorTextSame('.details-synthese > div:nth-child(2) h2 b', '3');
        self::assertSelectorTextContains('.regimes-synthese h2', 'Régimes');
        self::assertSelectorTextNotContains('.regimes-synthese', 'totaux anonymes');
        self::assertSelectorNotExists('.regimes-synthese .pastille-presence');

        $entityManager->remove($inscription);
        $entityManager->flush();
    }

    public function testUnePeriodeInverseeEstSignalee(): void
    {
        $client = self::createClient();
        $pilote = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        self::assertNotNull($pilote);
        $client->loginUser($pilote);

        $client->request('GET', '/synthese?debut=2095-06-14&fin=2095-06-12');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.alerte-erreur', 'date de fin');
        self::assertSelectorCount(1, '.jour-synthese');
    }
}
