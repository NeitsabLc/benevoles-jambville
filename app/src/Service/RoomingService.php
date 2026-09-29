<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Inscription;
use App\Entity\Utilisateur;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class RoomingService
{
    /** @var list<string> */
    private const BATIMENTS = [
        'Logement bénévole',
        'Château',
        'Orangerie',
        'Grande Ferme',
        'St Thomas',
        'Petite ferme',
        'Saint Louis',
    ];

    public function __construct(private readonly Connection $connexion)
    {
    }

    /** @return array<string, array{nom: string, capacite: int, batiment: string}> */
    public function getChambres(): array
    {
        $chambres = [];
        foreach ($this->connexion->fetchAllAssociative(
            <<<'SQL'
                SELECT code, nom, capacite, batiment
                FROM benevole_jambville.chambre_rooming
                ORDER BY ordre_affichage, nom
                SQL,
        ) as $ligne) {
            $chambres[(string) $ligne['code']] = [
                'nom' => (string) $ligne['nom'],
                'capacite' => (int) $ligne['capacite'],
                'batiment' => (string) $ligne['batiment'],
            ];
        }

        return $chambres;
    }

    /** @return list<string> */
    public function getBatiments(): array
    {
        return self::BATIMENTS;
    }

    public function creerChambre(string $nom, int $capacite, string $batiment, Utilisateur $auteur): void
    {
        $nom = trim((string) preg_replace('/\s+/u', ' ', $nom));
        if ('' === $nom || mb_strlen($nom) > 100) {
            throw new \DomainException('Le nom de la chambre est obligatoire et limité à 100 caractères.');
        }
        if ($capacite < 1 || $capacite > 100) {
            throw new \DomainException('La capacité doit être comprise entre 1 et 100 lits.');
        }
        if (!in_array($batiment, self::BATIMENTS, true)) {
            throw new \DomainException('Choisissez un bâtiment valide.');
        }

        $this->connexion->transactional(function (Connection $connexion) use ($nom, $capacite, $batiment, $auteur): void {
            $connexion->executeQuery(
                'SELECT pg_advisory_xact_lock(hashtext(:cle))',
                ['cle' => 'rooming|creation-chambre'],
            );
            $existe = $connexion->fetchOne(
                <<<'SQL'
                    SELECT 1
                    FROM benevole_jambville.chambre_rooming
                    WHERE batiment = :batiment
                      AND LOWER(nom) = LOWER(:nom)
                    SQL,
                ['batiment' => $batiment, 'nom' => $nom],
            );
            if (false !== $existe) {
                throw new \DomainException('Une chambre portant ce nom existe déjà dans ce bâtiment.');
            }

            $code = 'CHAMBRE_'.strtoupper(bin2hex(random_bytes(8)));
            $ordre = (int) $connexion->fetchOne(
                'SELECT COALESCE(MAX(ordre_affichage), 0) + 10 FROM benevole_jambville.chambre_rooming',
            );
            $connexion->insert('benevole_jambville.chambre_rooming', [
                'code' => $code,
                'nom' => $nom,
                'capacite' => $capacite,
                'batiment' => $batiment,
                'ordre_affichage' => $ordre,
                'cree_par_id' => $auteur->getId(),
            ]);
            $connexion->insert('benevole_jambville.configuration_chambre_rooming', [
                'chambre' => $code,
                'disponible_permanence' => true,
                'modifie_par_id' => $auteur->getId(),
            ], ['disponible_permanence' => ParameterType::BOOLEAN]);
        });
    }

    /**
     * @return array<string, array{
     *     nom: string,
     *     capacite: int,
     *     batiment: string,
     *     disponible_permanence: bool,
     *     periodes: list<array{id: string, date_debut: \DateTimeImmutable, date_fin: \DateTimeImmutable}>
     * }>
     */
    public function listerConfigurationDisponibilites(): array
    {
        $configuration = [];
        foreach ($this->getChambres() as $code => $chambre) {
            $configuration[$code] = [
                ...$chambre,
                'disponible_permanence' => false,
                'periodes' => [],
            ];
        }

        foreach ($this->connexion->fetchAllAssociative(
            'SELECT chambre, disponible_permanence FROM benevole_jambville.configuration_chambre_rooming ORDER BY chambre',
        ) as $ligne) {
            $code = (string) $ligne['chambre'];
            if (isset($configuration[$code])) {
                $configuration[$code]['disponible_permanence'] = (bool) $ligne['disponible_permanence'];
            }
        }

        foreach ($this->connexion->fetchAllAssociative(
            <<<'SQL'
                SELECT id, chambre, date_debut, date_fin
                FROM benevole_jambville.disponibilite_chambre_rooming
                ORDER BY chambre, date_debut, date_fin
                SQL,
        ) as $ligne) {
            $code = (string) $ligne['chambre'];
            if (!isset($configuration[$code])) {
                continue;
            }
            $configuration[$code]['periodes'][] = [
                'id' => (string) $ligne['id'],
                'date_debut' => new \DateTimeImmutable((string) $ligne['date_debut']),
                'date_fin' => new \DateTimeImmutable((string) $ligne['date_fin']),
            ];
        }

        return $configuration;
    }

    /** @return array<string, array<string, bool>> */
    public function trouverDisponibilites(\DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        $configuration = $this->listerConfigurationDisponibilites();
        $disponibilites = [];

        foreach ($configuration as $code => $chambre) {
            for ($date = $debut; $date <= $fin; $date = $date->modify('+1 day')) {
                $disponible = $chambre['disponible_permanence'];
                if (!$disponible) {
                    foreach ($chambre['periodes'] as $periode) {
                        if ($date >= $periode['date_debut'] && $date <= $periode['date_fin']) {
                            $disponible = true;
                            break;
                        }
                    }
                }
                $disponibilites[$code][$date->format('Y-m-d')] = $disponible;
            }
        }

        return $disponibilites;
    }

    public function rendreDisponiblePermanence(string $codeChambre, Utilisateur $auteur): void
    {
        $this->verifierChambre($codeChambre);

        $this->connexion->transactional(function (Connection $connexion) use ($codeChambre, $auteur): void {
            $this->verrouillerConfigurationChambre($connexion, $codeChambre);
            $connexion->update('benevole_jambville.configuration_chambre_rooming', [
                'disponible_permanence' => true,
                'modifie_par_id' => $auteur->getId(),
                'modifie_le' => (new \DateTimeImmutable())->format('Y-m-d H:i:sP'),
            ], ['chambre' => $codeChambre], ['disponible_permanence' => ParameterType::BOOLEAN]);
        });
    }

    public function rendreDisponibleParPeriodes(string $codeChambre, Utilisateur $auteur): void
    {
        $this->verifierChambre($codeChambre);

        $this->connexion->transactional(function (Connection $connexion) use ($codeChambre, $auteur): void {
            $this->verrouillerConfigurationChambre($connexion, $codeChambre);
            $affectationIncompatible = $connexion->fetchOne(
                <<<'SQL'
                    SELECT a.date_nuit
                    FROM benevole_jambville.affectation_rooming a
                    WHERE a.chambre = :chambre
                      AND a.date_nuit >= CURRENT_DATE
                      AND NOT EXISTS (
                          SELECT 1
                          FROM benevole_jambville.disponibilite_chambre_rooming d
                          WHERE d.chambre = a.chambre
                            AND a.date_nuit BETWEEN d.date_debut AND d.date_fin
                      )
                    ORDER BY a.date_nuit
                    LIMIT 1
                    SQL,
                ['chambre' => $codeChambre],
            );
            if (false !== $affectationIncompatible) {
                $date = new \DateTimeImmutable((string) $affectationIncompatible);
                throw new \DomainException(sprintf('Une personne est déjà planifiée dans cette chambre le %s. Ajoutez d’abord une période couvrant cette date.', $date->format('d/m/Y')));
            }

            $connexion->update('benevole_jambville.configuration_chambre_rooming', [
                'disponible_permanence' => false,
                'modifie_par_id' => $auteur->getId(),
                'modifie_le' => (new \DateTimeImmutable())->format('Y-m-d H:i:sP'),
            ], ['chambre' => $codeChambre], ['disponible_permanence' => ParameterType::BOOLEAN]);
        });
    }

    public function ajouterPeriodeDisponibilite(
        string $codeChambre,
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
        Utilisateur $auteur,
    ): void {
        $this->verifierChambre($codeChambre);
        if ($fin < $debut) {
            throw new \DomainException('La date de fin doit être postérieure ou égale à la date de début.');
        }

        $this->connexion->transactional(function (Connection $connexion) use ($codeChambre, $debut, $fin, $auteur): void {
            $this->verrouillerConfigurationChambre($connexion, $codeChambre);
            $debutFusionne = $debut;
            $finFusionnee = $fin;
            $periodes = $connexion->fetchAllAssociative(
                <<<'SQL'
                    SELECT id, date_debut, date_fin
                    FROM benevole_jambville.disponibilite_chambre_rooming
                    WHERE chambre = :chambre
                      AND date_debut <= :lendemain_fin
                      AND date_fin >= :veille_debut
                    ORDER BY date_debut
                    FOR UPDATE
                    SQL,
                [
                    'chambre' => $codeChambre,
                    'lendemain_fin' => $fin->modify('+1 day')->format('Y-m-d'),
                    'veille_debut' => $debut->modify('-1 day')->format('Y-m-d'),
                ],
            );
            foreach ($periodes as $periode) {
                $debutExistant = new \DateTimeImmutable((string) $periode['date_debut']);
                $finExistante = new \DateTimeImmutable((string) $periode['date_fin']);
                $debutFusionne = min($debutFusionne, $debutExistant);
                $finFusionnee = max($finFusionnee, $finExistante);
                $connexion->delete('benevole_jambville.disponibilite_chambre_rooming', ['id' => $periode['id']]);
            }

            $connexion->insert('benevole_jambville.disponibilite_chambre_rooming', [
                'chambre' => $codeChambre,
                'date_debut' => $debutFusionne->format('Y-m-d'),
                'date_fin' => $finFusionnee->format('Y-m-d'),
                'modifie_par_id' => $auteur->getId(),
            ]);
        });
    }

    public function supprimerPeriodeDisponibilite(string $id, Utilisateur $auteur): void
    {
        $this->connexion->transactional(function (Connection $connexion) use ($id, $auteur): void {
            $periode = $connexion->fetchAssociative(
                <<<'SQL'
                    SELECT id, chambre, date_debut, date_fin
                    FROM benevole_jambville.disponibilite_chambre_rooming
                    WHERE id = :id
                    FOR UPDATE
                    SQL,
                ['id' => $id],
            );
            if (false === $periode) {
                throw new \DomainException('Cette période de disponibilité n’existe plus.');
            }

            $codeChambre = (string) $periode['chambre'];
            $this->verrouillerConfigurationChambre($connexion, $codeChambre);
            $permanente = (bool) $connexion->fetchOne(
                'SELECT disponible_permanence FROM benevole_jambville.configuration_chambre_rooming WHERE chambre = :chambre',
                ['chambre' => $codeChambre],
            );
            if (!$permanente) {
                $affectationIncompatible = $connexion->fetchOne(
                    <<<'SQL'
                        SELECT a.date_nuit
                        FROM benevole_jambville.affectation_rooming a
                        WHERE a.chambre = :chambre
                          AND a.date_nuit >= CURRENT_DATE
                          AND a.date_nuit BETWEEN :debut AND :fin
                          AND NOT EXISTS (
                              SELECT 1
                              FROM benevole_jambville.disponibilite_chambre_rooming d
                              WHERE d.chambre = a.chambre
                                AND d.id <> :periode
                                AND a.date_nuit BETWEEN d.date_debut AND d.date_fin
                          )
                        ORDER BY a.date_nuit
                        LIMIT 1
                        SQL,
                    [
                        'chambre' => $codeChambre,
                        'debut' => $periode['date_debut'],
                        'fin' => $periode['date_fin'],
                        'periode' => $id,
                    ],
                );
                if (false !== $affectationIncompatible) {
                    $date = new \DateTimeImmutable((string) $affectationIncompatible);
                    throw new \DomainException(sprintf('Cette période ne peut pas être supprimée : une personne est planifiée le %s.', $date->format('d/m/Y')));
                }
            }

            $connexion->delete('benevole_jambville.disponibilite_chambre_rooming', ['id' => $id]);
            $connexion->update('benevole_jambville.configuration_chambre_rooming', [
                'modifie_par_id' => $auteur->getId(),
                'modifie_le' => (new \DateTimeImmutable())->format('Y-m-d H:i:sP'),
            ], ['chambre' => $codeChambre]);
        });
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
