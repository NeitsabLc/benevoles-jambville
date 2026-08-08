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
