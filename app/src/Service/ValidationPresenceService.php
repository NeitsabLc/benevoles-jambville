<?php

declare(strict_types=1);

namespace App\Service;

final class ValidationPresenceService
{
    public const LONGUEUR_COMMENTAIRE_MAX = 1_000;

    /**
     * @return array{
     *     date_debut: ?\DateTimeImmutable,
     *     date_fin: ?\DateTimeImmutable,
     *     type_couchage: string,
     *     commentaire: ?string,
     *     erreurs: list<string>
     * }
     */
    public function valider(
        string $dateDebutBrute,
        string $dateFinBrute,
        string $typeCouchage,
        string $commentaireBrut,
    ): array {
        $erreurs = [];
        $dateDebut = $this->lireDate($dateDebutBrute);
        $dateFin = $this->lireDate($dateFinBrute);
        if (null === $dateDebut || null === $dateFin) {
            $erreurs[] = 'Les dates de présence sont obligatoires.';
        } elseif ($dateFin < $dateDebut) {
            $erreurs[] = 'La date de fin doit être postérieure ou égale à la date de début.';
        } elseif ($dateFin > $dateDebut->modify('+366 days')) {
            $erreurs[] = 'La durée d’une présence est limitée à un an.';
        }

        if (!in_array($typeCouchage, ['DUR', 'TENTE'], true)) {
            $erreurs[] = 'Choisissez un type de couchage.';
        }

        $commentaire = trim($commentaireBrut) ?: null;
        if (null !== $commentaire && mb_strlen($commentaire) > self::LONGUEUR_COMMENTAIRE_MAX) {
            $erreurs[] = 'Le commentaire est limité à 1 000 caractères.';
        }

        return [
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'type_couchage' => $typeCouchage,
            'commentaire' => $commentaire,
            'erreurs' => $erreurs,
        ];
    }

    private function lireDate(string $valeur): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $valeur);

        return false !== $date && $date->format('Y-m-d') === $valeur ? $date : null;
    }
}
