<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Inscription;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Inscription> */
final class InscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Inscription::class);
    }

    /** @return list<Inscription> */
    public function findPourCalendrier(\DateTimeImmutable $debut, \DateTimeImmutable $fin, ?string $filtre): array
    {
        $requete = $this->createQueryBuilder('i')
            ->addSelect('u', 't')
            ->leftJoin('i.utilisateur', 'u')
            ->leftJoin('i.thematique', 't')
            ->andWhere('i.actif = true')
            ->andWhere('i.dateDebut <= :fin AND i.dateFin >= :debut')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->orderBy('i.dateDebut', 'ASC');

        if ($filtre === 'compa') {
            $requete->andWhere('i.type = :type')->setParameter('type', 'COMPAGNON');
        } elseif ($filtre !== null) {
            $requete->andWhere('t.id = :thematique')->setParameter('thematique', $filtre);
        }

        return $requete->getQuery()->getResult();
    }

    public function chevauchePour(Utilisateur $utilisateur, \DateTimeImmutable $debut, \DateTimeImmutable $fin, ?Inscription $inscriptionIgnoree = null): bool
    {
        $requete = $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.utilisateur = :utilisateur')
            ->andWhere('i.type = :type AND i.actif = true')
            ->andWhere('i.dateDebut <= :fin AND i.dateFin >= :debut')
            ->setParameter('utilisateur', $utilisateur)
            ->setParameter('type', 'INDIVIDUELLE')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin);

        if ($inscriptionIgnoree !== null) {
            $requete->andWhere('i.id != :inscriptionIgnoree')->setParameter('inscriptionIgnoree', $inscriptionIgnoree->getId());
        }

        return (int) $requete->getQuery()->getSingleScalarResult() > 0;
    }
}
