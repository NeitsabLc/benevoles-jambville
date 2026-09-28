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
        $identites = $crawler->filter('.table-presences-synthese tbody th')->each(
            static fn ($presence): string => trim($presence->text()),
        );
        self::assertSame(['Camille B.', 'Dominique P.', 'Sasha A.'], $identites);
        self::assertSelectorCount(1, '.table-presences-synthese');

        foreach ($inscriptionsCreees as $inscription) {
            $entityManager->remove($inscription);
        }
        $entityManager->flush();
    }

    public function testUneJourneeChargeeAfficheCinqPresencesPuisUnDetailUnique(): void
    {
        $client = self::createClient();
        $pilote = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        self::assertNotNull($pilote);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $inscriptions = [];

        try {
            for ($numero = 1; $numero <= 7; ++$numero) {
                $inscription = Inscription::compagnon(
                    $pilote,
                    'Équipe synthèse '.$numero,
                    $numero,
                    new \DateTimeImmutable('2098-11-08'),
                    new \DateTimeImmutable('2098-11-08'),
                    0 === $numero % 2 ? 'DUR' : 'TENTE',
                    0,
                    0,
                    0,
                    null,
                );
                $inscriptions[] = $inscription;
                $entityManager->persist($inscription);
            }
            $entityManager->flush();
            $client->loginUser($pilote);

            $client->request('GET', '/synthese?debut=2098-11-08&fin=2098-11-08');

            self::assertResponseIsSuccessful();
            self::assertSelectorCount(5, '.apercu-presences-synthese .pastille-presence');
            self::assertSelectorTextContains('.details-presences-synthese summary', 'Voir les 2 autres inscriptions');
            self::assertSelectorCount(7, '.details-presences-synthese .table-presences-synthese tbody tr');
            self::assertSelectorTextContains('.date-synthese', '28 personnes · 7 inscriptions');
        } finally {
            foreach ($inscriptions as $inscription) {
                $entityManager->remove($inscription);
            }
            $entityManager->flush();
        }
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

        $benevole->modifierProfil(
            $benevole->getTelephone(),
            $benevole->isVegetarien(),
            $benevole->isSansLactose(),
            $benevole->isSansGluten(),
            $benevole->getRegimeAutre(),
            'Lit en rez-de-chaussée',
        );
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
        self::assertSelectorTextContains('.table-presences-synthese tbody tr', 'Camille B.');
        self::assertSelectorTextContains('.table-presences-synthese tbody tr', '3');
        self::assertSelectorTextContains('.table-presences-synthese tbody tr', 'En dur');
        self::assertSelectorTextContains('.table-presences-synthese tbody tr', 'Lit en rez-de-chaussée');
        self::assertSelectorTextContains('.regimes-synthese h2', 'Régimes');
        self::assertSelectorTextNotContains('.regimes-synthese', 'totaux anonymes');
        self::assertSelectorNotExists('.regimes-synthese .pastille-presence');

        $entityManager->remove($inscription);
        $benevole->modifierProfil(
            $benevole->getTelephone(),
            $benevole->isVegetarien(),
            $benevole->isSansLactose(),
            $benevole->isSansGluten(),
            $benevole->getRegimeAutre(),
            null,
        );
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
