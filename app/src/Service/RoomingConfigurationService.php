<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Utilisateur;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class RoomingConfigurationService
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

    private function verrouillerConfigurationChambre(Connection $connexion, string $codeChambre): void
    {
        $connexion->executeQuery(
            'SELECT pg_advisory_xact_lock(hashtext(:cle))',
            ['cle' => 'rooming|configuration|'.$codeChambre],
        );
    }

    private function verifierChambre(string $codeChambre): void
    {
        $existe = $this->connexion->fetchOne(
            'SELECT 1 FROM benevole_jambville.chambre_rooming WHERE code = :code',
            ['code' => $codeChambre],
        );
        if (false === $existe) {
            throw new \DomainException('Choisissez une chambre valide.');
        }
    }
}
