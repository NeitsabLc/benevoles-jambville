<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PersonnePermanence;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PersonnePermanence> */
final class PersonnePermanenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PersonnePermanence::class);
    }

    /** @return list<PersonnePermanence> */
    public function findActives(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.actif = true')
            ->orderBy('p.ordreAffichage', 'ASC')
            ->addOrderBy('p.nom', 'ASC')
            ->getQuery()->getResult();
    }
}
