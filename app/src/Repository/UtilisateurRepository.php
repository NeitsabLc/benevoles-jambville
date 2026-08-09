<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;

/** @extends ServiceEntityRepository<Utilisateur> */
final class UtilisateurRepository extends ServiceEntityRepository implements UserLoaderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Utilisateur::class);
    }

    public function loadUserByIdentifier(string $identifier): ?Utilisateur
    {
        return $this->createQueryBuilder('u')
            ->andWhere('LOWER(u.email) = LOWER(:email)')
            ->setParameter('email', trim($identifier))
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByActivationToken(string $token): ?Utilisateur
    {
        return $this->findOneBy(['jetonActivation' => hash('sha256', $token)]);
    }

    /** @return list<Utilisateur> */
    public function findActifsPourInscription(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.actif = true')
            ->orderBy('u.nom', 'ASC')
            ->addOrderBy('u.prenom', 'ASC')
            ->getQuery()->getResult();
    }

    /** @return list<Utilisateur> */
    public function findTousPourAdministration(): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.nom', 'ASC')
            ->addOrderBy('u.prenom', 'ASC')
            ->getQuery()->getResult();
    }

    public function purgerDesactivesAvant(\DateTimeImmutable $limite): int
    {
        $connexion = $this->getEntityManager()->getConnection();

        return $connexion->transactional(function () use ($connexion, $limite): int {
            $parametres = ['limite' => $limite->format('Y-m-d H:i:sP')];
            $condition = 'SELECT id FROM benevole_jambville.utilisateur WHERE NOT actif AND desactive_le <= :limite';

            $connexion->executeStatement("DELETE FROM benevole_jambville.inscription WHERE utilisateur_id IN ($condition) OR cree_par_id IN ($condition) OR modifie_par_id IN ($condition)", $parametres);
            $connexion->executeStatement("DELETE FROM benevole_jambville.journee WHERE modifie_par_id IN ($condition)", $parametres);
            $connexion->executeStatement("DELETE FROM benevole_jambville.journal_audit WHERE utilisateur_id IN ($condition) OR objet_id IN ($condition)", $parametres);

            return $connexion->executeStatement(
                "DELETE FROM benevole_jambville.utilisateur WHERE id IN ($condition)",
                $parametres,
            );
        });
    }
}
