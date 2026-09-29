<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Inscription;
use App\Entity\Utilisateur;
use Doctrine\DBAL\Connection;

final class RoomingService
{
    public function __construct(private readonly Connection $connexion)
    {
    }

    /** @return array<string, array<string, array{chambre: string, nombre_places: int}>> */
    public function trouverAffectations(\DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        $affectations = [];
        $lignes = $this->connexion->fetchAllAssociative(
            <<<'SQL'
                SELECT a.inscription_id, a.date_nuit, a.chambre, a.nombre_places
                FROM benevole_jambville.affectation_rooming a
                INNER JOIN benevole_jambville.inscription i ON i.id = a.inscription_id
                WHERE i.actif = TRUE
                  AND i.type = 'INDIVIDUELLE'
                  AND i.type_couchage = 'DUR'
                  AND i.date_debut < i.date_fin
                  AND a.date_nuit BETWEEN :debut AND :fin
                  AND i.date_debut <= a.date_nuit
                  AND i.date_fin >= a.date_nuit
                ORDER BY a.date_nuit, a.chambre, a.inscription_id
                SQL,
            [
                'debut' => $debut->format('Y-m-d'),
                'fin' => $fin->format('Y-m-d'),
            ],
        );

        foreach ($lignes as $ligne) {
            $affectations[(string) $ligne['date_nuit']][(string) $ligne['inscription_id']] = [
                'chambre' => (string) $ligne['chambre'],
                'nombre_places' => (int) $ligne['nombre_places'],
            ];
        }

        return $affectations;
    }

    /** @return list<array{date: \DateTimeImmutable, chambre: string, nom: string, batiment: string}> */
    public function trouverAffectationsPour(Inscription $inscription): array
    {
        return array_map(
            static fn (array $ligne): array => [
                'date' => new \DateTimeImmutable((string) $ligne['date_nuit']),
                'chambre' => (string) $ligne['chambre'],
                'nom' => (string) $ligne['nom'],
                'batiment' => (string) $ligne['batiment'],
            ],
            $this->connexion->fetchAllAssociative(
                <<<'SQL'
                    SELECT a.date_nuit, a.chambre, c.nom, c.batiment
                    FROM benevole_jambville.affectation_rooming a
                    INNER JOIN benevole_jambville.chambre_rooming c ON c.code = a.chambre
                    WHERE a.inscription_id = :inscription
                      AND a.date_nuit BETWEEN :debut AND :fin
                    ORDER BY a.date_nuit, c.nom
                    SQL,
                [
                    'inscription' => $inscription->getId(),
                    'debut' => $inscription->getDateDebut()->format('Y-m-d'),
                    'fin' => $inscription->getDateFin()->format('Y-m-d'),
                ],
            ),
        );
    }

    public function affecter(Inscription $inscription, ?string $codeChambre, Utilisateur $auteur): void
    {
        $this->affecterPeriode($inscription, $codeChambre, $auteur);
    }

    public function affecterPeriode(Inscription $inscription, ?string $codeChambre, Utilisateur $auteur): void
    {
        $this->verifierPresenceEligible($inscription);
        $this->verifierChambre($codeChambre);

        $this->connexion->transactional(function (Connection $connexion) use ($inscription, $auteur, $codeChambre): void {
            $this->verrouillerInscription($connexion, $inscription);
            if (null !== $codeChambre) {
                $this->verrouillerConfigurationChambre($connexion, $codeChambre);
                for ($date = $inscription->getDateDebut(); $date <= $inscription->getDateFin(); $date = $date->modify('+1 day')) {
                    $this->verrouillerChambre($connexion, $date, $codeChambre);
                }
                $this->verifierDisponibilite(
                    $connexion,
                    $codeChambre,
                    $inscription->getDateDebut(),
                    $inscription->getDateFin(),
                );
                $this->verifierCapacite(
                    $connexion,
                    $inscription,
                    $codeChambre,
                    $inscription->getDateDebut(),
                    $inscription->getDateFin(),
                );
            }

            $connexion->delete('benevole_jambville.affectation_rooming', ['inscription_id' => $inscription->getId()]);
            if (null !== $codeChambre) {
                for ($date = $inscription->getDateDebut(); $date <= $inscription->getDateFin(); $date = $date->modify('+1 day')) {
                    $this->insererAffectation($connexion, $date, $inscription, $codeChambre, $auteur);
                }
            }
        });
    }

    public function affecterNuit(
        \DateTimeImmutable $date,
        Inscription $inscription,
        ?string $codeChambre,
        Utilisateur $auteur,
    ): void {
        $this->verifierPresenceEligible($inscription);
        $this->verifierDateDuSejour($date, $inscription);
        $this->verifierChambre($codeChambre);

        $this->connexion->transactional(function (Connection $connexion) use ($date, $inscription, $codeChambre, $auteur): void {
            $this->verrouillerInscription($connexion, $inscription);
            if (null !== $codeChambre) {
                $this->verrouillerConfigurationChambre($connexion, $codeChambre);
                $this->verrouillerChambre($connexion, $date, $codeChambre);
                $this->verifierDisponibilite($connexion, $codeChambre, $date, $date);
                $this->verifierCapacite($connexion, $inscription, $codeChambre, $date, $date);
            }

            $connexion->delete('benevole_jambville.affectation_rooming', [
                'inscription_id' => $inscription->getId(),
                'date_nuit' => $date->format('Y-m-d'),
            ]);
            if (null !== $codeChambre) {
                $this->insererAffectation($connexion, $date, $inscription, $codeChambre, $auteur);
            }
        });
    }

    /** @return array{chambre: string|null, complete: bool, nombre_nuits: int} */
    public function resumerAffectation(Inscription $inscription): array
    {
        $resume = $this->connexion->fetchAssociative(
            <<<'SQL'
                SELECT COUNT(*) AS nombre_nuits,
                       COUNT(DISTINCT chambre) AS nombre_chambres,
                       MIN(chambre) AS chambre
                FROM benevole_jambville.affectation_rooming
                WHERE inscription_id = :inscription
                  AND date_nuit BETWEEN :debut AND :fin
                SQL,
            [
                'inscription' => $inscription->getId(),
                'debut' => $inscription->getDateDebut()->format('Y-m-d'),
                'fin' => $inscription->getDateFin()->format('Y-m-d'),
            ],
        );
        $nombreNuits = (int) ($resume['nombre_nuits'] ?? 0);
        $nombreAttendu = $inscription->getDateDebut()->diff($inscription->getDateFin())->days + 1;

        return [
            'chambre' => null !== ($resume['chambre'] ?? null) ? (string) $resume['chambre'] : null,
            'complete' => $nombreNuits === $nombreAttendu && 1 === (int) ($resume['nombre_chambres'] ?? 0),
            'nombre_nuits' => $nombreNuits,
        ];
    }

    public function synchroniserApresModification(
        Inscription $inscription,
        int $ancienEffectif,
        ?string $ancienUtilisateurId,
        \DateTimeImmutable $ancienneDateDebut,
        \DateTimeImmutable $ancienneDateFin,
    ): void {
        if ('DUR' !== $inscription->getTypeCouchage() || $ancienUtilisateurId !== $inscription->getUtilisateur()?->getId()) {
            $this->supprimerPour($inscription);

            return;
        }

        $nouvelEffectif = $this->effectif($inscription);
        if ($nouvelEffectif === $ancienEffectif
            && $ancienneDateDebut == $inscription->getDateDebut()
            && $ancienneDateFin == $inscription->getDateFin()) {
            return;
        }

        // Toute modification de période ou d'effectif impose une nouvelle
        // vérification de capacité sur l'intégralité du séjour.
        $this->supprimerPour($inscription);
    }

    public function supprimerPour(Inscription $inscription): void
    {
        $this->connexion->delete('benevole_jambville.affectation_rooming', ['inscription_id' => $inscription->getId()]);
    }

    private function verifierCapacite(
        Connection $connexion,
        Inscription $inscription,
        string $codeChambre,
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
    ): void {
        $chambre = $this->obtenirChambre($connexion, $codeChambre);
        $saturation = $connexion->fetchAssociative(
            <<<'SQL'
                SELECT date_nuit, COALESCE(SUM(nombre_places), 0) AS occupees
                FROM benevole_jambville.affectation_rooming
                WHERE chambre = :chambre
                  AND inscription_id <> :inscription
                  AND date_nuit BETWEEN :debut AND :fin
                GROUP BY date_nuit
                HAVING SUM(nombre_places) + :effectif > :capacite
                ORDER BY date_nuit
                LIMIT 1
                SQL,
            [
                'chambre' => $codeChambre,
                'inscription' => $inscription->getId(),
                'debut' => $debut->format('Y-m-d'),
                'fin' => $fin->format('Y-m-d'),
                'effectif' => $this->effectif($inscription),
                'capacite' => $chambre['capacite'],
            ],
        );
        if (false === $saturation) {
            return;
        }

        $occupees = (int) $saturation['occupees'];
        $disponibles = max(0, $chambre['capacite'] - $occupees);
        $dateSaturee = new \DateTimeImmutable((string) $saturation['date_nuit']);

        throw new \DomainException(sprintf('La chambre %s ne dispose plus que de %d lit%s le %s. Choisissez une chambre disponible pour la portée demandée.', $chambre['nom'], $disponibles, $disponibles > 1 ? 's' : '', $dateSaturee->format('d/m/Y')));
    }

    private function verifierDisponibilite(
        Connection $connexion,
        string $codeChambre,
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
    ): void {
        $chambre = $this->obtenirChambre($connexion, $codeChambre);
        $dateIndisponible = $connexion->fetchOne(
            <<<'SQL'
                SELECT dates.date_nuit::date
                FROM generate_series(
                    CAST(:debut AS date),
                    CAST(:fin AS date),
                    INTERVAL '1 day'
                ) AS dates(date_nuit)
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM benevole_jambville.configuration_chambre_rooming c
                    WHERE c.chambre = :chambre
                      AND (
                          c.disponible_permanence = TRUE
                          OR EXISTS (
                              SELECT 1
                              FROM benevole_jambville.disponibilite_chambre_rooming d
                              WHERE d.chambre = c.chambre
                                AND dates.date_nuit::date BETWEEN d.date_debut AND d.date_fin
                          )
                      )
                )
                ORDER BY dates.date_nuit
                LIMIT 1
                SQL,
            [
                'chambre' => $codeChambre,
                'debut' => $debut->format('Y-m-d'),
                'fin' => $fin->format('Y-m-d'),
            ],
        );
        if (false === $dateIndisponible) {
            return;
        }

        $date = new \DateTimeImmutable((string) $dateIndisponible);
        throw new \DomainException(sprintf('La chambre %s n’est pas disponible le %s. Choisissez une nuit comprise dans sa période de mise à disposition.', $chambre['nom'], $date->format('d/m/Y')));
    }

    private function insererAffectation(
        Connection $connexion,
        \DateTimeImmutable $date,
        Inscription $inscription,
        string $codeChambre,
        Utilisateur $auteur,
    ): void {
        $connexion->insert('benevole_jambville.affectation_rooming', [
            'inscription_id' => $inscription->getId(),
            'date_nuit' => $date->format('Y-m-d'),
            'chambre' => $codeChambre,
            'nombre_places' => $this->effectif($inscription),
            'modifie_par_id' => $auteur->getId(),
        ]);
    }

    private function verrouillerInscription(Connection $connexion, Inscription $inscription): void
    {
        $connexion->executeQuery(
            'SELECT pg_advisory_xact_lock(hashtext(:cle))',
            ['cle' => 'rooming|inscription|'.$inscription->getId()],
        );
    }

    private function verrouillerChambre(Connection $connexion, \DateTimeImmutable $date, string $codeChambre): void
    {
        $connexion->executeQuery(
            'SELECT pg_advisory_xact_lock(hashtext(:cle))',
            ['cle' => 'rooming|'.$date->format('Y-m-d').'|'.$codeChambre],
        );
    }

    private function verrouillerConfigurationChambre(Connection $connexion, string $codeChambre): void
    {
        $connexion->executeQuery(
            'SELECT pg_advisory_xact_lock(hashtext(:cle))',
            ['cle' => 'rooming|configuration|'.$codeChambre],
        );
    }

    private function verifierChambre(?string $codeChambre): void
    {
        if (null !== $codeChambre) {
            $this->obtenirChambre($this->connexion, $codeChambre);
        }
    }

    /** @return array{nom: string, capacite: int, batiment: string} */
    private function obtenirChambre(Connection $connexion, string $codeChambre): array
    {
        $chambre = $connexion->fetchAssociative(
            <<<'SQL'
                SELECT nom, capacite, batiment
                FROM benevole_jambville.chambre_rooming
                WHERE code = :code
                SQL,
            ['code' => $codeChambre],
        );
        if (false === $chambre) {
            throw new \DomainException('Choisissez une chambre valide.');
        }

        return [
            'nom' => (string) $chambre['nom'],
            'capacite' => (int) $chambre['capacite'],
            'batiment' => (string) $chambre['batiment'],
        ];
    }

    private function verifierDateDuSejour(\DateTimeImmutable $date, Inscription $inscription): void
    {
        if ($date < $inscription->getDateDebut() || $date > $inscription->getDateFin()) {
            throw new \DomainException('Cette personne n’est pas présente à la date sélectionnée.');
        }
    }

    private function verifierPresenceEligible(Inscription $inscription): void
    {
        if (!$inscription->isActif()
            || 'INDIVIDUELLE' !== $inscription->getType()
            || 'DUR' !== $inscription->getTypeCouchage()
            || $inscription->getDateDebut() == $inscription->getDateFin()) {
            throw new \DomainException('Cette présence ne nécessite pas de chambre.');
        }
    }

    private function effectif(Inscription $inscription): int
    {
        return 'COMPAGNON' === $inscription->getType()
            ? (int) $inscription->getNombrePersonnes()
            : 1 + $inscription->getNombreEnfants();
    }
}
