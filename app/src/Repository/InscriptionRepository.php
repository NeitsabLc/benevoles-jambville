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
            ->orderBy('u.prenom', 'ASC')
            ->addOrderBy('u.nom', 'ASC')
            ->addOrderBy('i.nomEquipeCompa', 'ASC')
            ->addOrderBy('i.dateDebut', 'ASC')
            ->addOrderBy('i.id', 'ASC');

        if ('compa' === $filtre) {
            $requete->andWhere('i.type = :type')->setParameter('type', 'COMPAGNON');
        } elseif (null !== $filtre) {
            $requete->andWhere('t.id = :thematique')->setParameter('thematique', $filtre);
        }

        return $requete->getQuery()->getResult();
    }

    /** @return list<Inscription> */
    public function findPourSynthese(\DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        return $this->createQueryBuilder('i')
            ->addSelect('u', 'r')
            ->leftJoin('i.utilisateur', 'u')
            ->leftJoin('i.repas', 'r')
            ->andWhere('i.actif = true')
            ->andWhere('i.dateDebut <= :fin AND i.dateFin >= :debut')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->orderBy('u.prenom', 'ASC')
            ->addOrderBy('u.nom', 'ASC')
            ->addOrderBy('i.nomEquipeCompa', 'ASC')
            ->addOrderBy('i.dateDebut', 'ASC')
            ->addOrderBy('i.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Inscription> */
    public function findPourRooming(\DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        return $this->createQueryBuilder('i')
            ->addSelect('u')
            ->leftJoin('i.utilisateur', 'u')
            ->andWhere('i.actif = true')
            ->andWhere('i.type = :type')
            ->andWhere('i.typeCouchage = :couchage')
            ->andWhere('i.dateDebut < i.dateFin')
            ->andWhere('i.dateDebut <= :fin AND i.dateFin >= :debut')
            ->setParameter('type', 'INDIVIDUELLE')
            ->setParameter('couchage', 'DUR')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->orderBy('u.prenom', 'ASC')
            ->addOrderBy('u.nom', 'ASC')
            ->addOrderBy('i.nomEquipeCompa', 'ASC')
            ->addOrderBy('i.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findProchainePourUtilisateur(Utilisateur $utilisateur, \DateTimeImmutable $dateReference): ?Inscription
    {
        return $this->createQueryBuilder('i')
            ->addSelect('t')
            ->leftJoin('i.thematique', 't')
            ->andWhere('i.actif = true')
            ->andWhere('i.type = :type')
            ->andWhere('i.utilisateur = :utilisateur')
            ->andWhere('i.dateFin >= :dateReference')
            ->setParameter('type', 'INDIVIDUELLE')
            ->setParameter('utilisateur', $utilisateur)
            ->setParameter('dateReference', $dateReference)
            ->orderBy('i.dateDebut', 'ASC')
            ->addOrderBy('i.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
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

        if (null !== $inscriptionIgnoree) {
            $requete->andWhere('i.id != :inscriptionIgnoree')->setParameter('inscriptionIgnoree', $inscriptionIgnoree->getId());
        }

        return (int) $requete->getQuery()->getSingleScalarResult() > 0;
    }
}
