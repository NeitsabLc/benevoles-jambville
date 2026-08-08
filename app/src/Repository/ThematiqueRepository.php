<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Thematique;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Thematique> */
final class ThematiqueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Thematique::class);
    }

    /** @return list<Thematique> */
    public function findActives(): array
    {
        $this->desactiverExpirees();

        return $this->findBy(['actif' => true], ['ordreAffichage' => 'ASC', 'nom' => 'ASC']);
    }

    /** @return list<Thematique> */
    public function findToutes(): array
    {
        $this->desactiverExpirees();

        return $this->findBy([], ['actif' => 'DESC', 'ordreAffichage' => 'ASC', 'nom' => 'ASC']);
    }

    public function desactiverExpirees(?\DateTimeImmutable $aujourdhui = null): int
    {
        $dateLimite = ($aujourdhui ?? new \DateTimeImmutable('today'))->modify('-1 day');

        return $this->createQueryBuilder('thematique')
            ->update()
            ->set('thematique.actif', ':inactif')
            ->where('thematique.actif = :actif')
            ->andWhere('thematique.dateFinEvenement IS NOT NULL')
            ->andWhere('thematique.dateFinEvenement < :dateLimite')
            ->setParameter('actif', true)
            ->setParameter('inactif', false)
            ->setParameter('dateLimite', $dateLimite, \Doctrine\DBAL\Types\Types::DATE_IMMUTABLE)
            ->getQuery()
            ->execute();
    }

    /** @return list<Thematique> */
    public function findDisponiblesPour(\DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        $actives = $this->findActives();
        $exclusivesChevauchantes = array_values(array_filter($actives, static fn (Thematique $thematique): bool => $thematique->isExclusiveSurPeriode() && $thematique->chevauche($debut, $fin)));
        if ($exclusivesChevauchantes !== []) {
            return array_values(array_filter($exclusivesChevauchantes, static fn (Thematique $thematique): bool => $thematique->estCompatibleAvec($debut, $fin)));
        }

        $compatibles = array_values(array_filter($actives, static fn (Thematique $thematique): bool => $thematique->estCompatibleAvec($debut, $fin)));

        usort($compatibles, static fn (Thematique $a, Thematique $b): int => ($b->isEvenement() <=> $a->isEvenement()) ?: ($a->getOrdreAffichage() <=> $b->getOrdreAffichage()) ?: strcasecmp($a->getNom(), $b->getNom()));
        return $compatibles;
    }

    /** @return list<Thematique> */
    public function findExclusivesChevauchant(\DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        return array_values(array_filter($this->findActives(), static fn (Thematique $thematique): bool => $thematique->isExclusiveSurPeriode() && $thematique->chevauche($debut, $fin)));
    }
}
