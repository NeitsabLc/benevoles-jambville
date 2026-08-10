<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;

final class HistoriquePresenceAnonymeRepository
{
    private Connection $connexion;

    public function __construct(ManagerRegistry $registry)
    {
        $this->connexion = $registry->getConnection();
    }

    public function archiverEtPurgerCampagne(\DateTimeImmutable $debut, \DateTimeImmutable $fin): int
    {
        return $this->connexion->transactional(function () use ($debut, $fin): int {
            $parametres = [
                'debut' => $debut->format('Y-m-d'),
                'fin' => $fin->format('Y-m-d'),
            ];

            $this->connexion->executeStatement(<<<'SQL'
                INSERT INTO benevole_jambville.historique_presence_anonyme
                    (date_journee, thematique, nombre_benevoles, nombre_compagnons)
                SELECT jours.date_journee::date,
                       CASE WHEN inscription.type = 'INDIVIDUELLE' THEN thematique.nom END,
                       (COUNT(*) FILTER (WHERE inscription.type = 'INDIVIDUELLE'))::integer,
                       (COALESCE(SUM(inscription.nombre_personnes) FILTER (WHERE inscription.type = 'COMPAGNON'), 0))::integer
                FROM benevole_jambville.inscription
                LEFT JOIN benevole_jambville.thematique ON thematique.id = inscription.thematique_id
                CROSS JOIN LATERAL generate_series(
                    GREATEST(inscription.date_debut, CAST(:debut AS date)),
                    LEAST(inscription.date_fin, CAST(:fin AS date)),
                    INTERVAL '1 day'
                ) AS jours(date_journee)
                WHERE inscription.actif
                  AND inscription.date_debut <= CAST(:fin AS date)
                  AND inscription.date_fin >= CAST(:debut AS date)
                GROUP BY jours.date_journee, CASE WHEN inscription.type = 'INDIVIDUELLE' THEN thematique.nom END
                -- L'archive d'une campagne est immuable. Après la première
                -- exécution, les inscriptions entièrement contenues ont été
                -- supprimées et ne permettraient plus un recalcul complet.
                ON CONFLICT (date_journee, thematique) DO NOTHING
                SQL, $parametres);

            return $this->connexion->executeStatement(<<<'SQL'
                DELETE FROM benevole_jambville.inscription
                WHERE date_debut >= CAST(:debut AS date)
                  AND date_fin <= CAST(:fin AS date)
                SQL, $parametres);
        });
    }
}
