<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Journee;
use App\Entity\PersonnePermanence;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class JourneeTest extends KernelTestCase
{
    public function testUnePermanencePeutEtreEnregistree(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $utilisateurs = self::getContainer()->get(UtilisateurRepository::class);
        $utilisateur = $utilisateurs->findOneBy(['codeAdherent' => 'DEV-PILOTE']);
        self::assertNotNull($utilisateur);

        $entityManager->getConnection()->beginTransaction();
        try {
            $personne = new PersonnePermanence('Test permanence '.bin2hex(random_bytes(4)));
            $date = new \DateTimeImmutable('2099-12-31');
            $journee = new Journee($date, $utilisateur);
            $journee->definirPermanence($personne, $utilisateur);
            $entityManager->persist($personne);
            $entityManager->persist($journee);
            $entityManager->flush();

            self::assertSame('2099-12-31', $journee->getDateJournee()->format('Y-m-d'));
        } finally {
            $entityManager->getConnection()->rollBack();
        }
    }
}
