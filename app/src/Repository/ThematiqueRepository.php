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
        return $this->findBy(['actif' => true], ['ordreAffichage' => 'ASC', 'nom' => 'ASC']);
    }
}
