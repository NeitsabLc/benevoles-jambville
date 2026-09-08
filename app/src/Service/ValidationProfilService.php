<?php

declare(strict_types=1);

namespace App\Service;

final class ValidationProfilService
{
    public const LONGUEUR_TEXTE_LIBRE_MAX = 1_000;

    /**
     * @return array{
     *     telephone: ?string,
     *     regime_autre: ?string,
     *     besoin_couchage: ?string,
     *     erreurs: list<string>
     * }
     */
    public function valider(string $telephoneBrut, string $regimeAutreBrut, string $besoinCouchageBrut): array
    {
        $erreurs = [];
        $telephone = trim($telephoneBrut) ?: null;
        $regimeAutre = trim($regimeAutreBrut) ?: null;
        $besoinCouchage = trim($besoinCouchageBrut) ?: null;

        if (null !== $telephone && mb_strlen($telephone) > 30) {
            $erreurs[] = 'Le numéro de téléphone ne peut pas dépasser 30 caractères.';
        } elseif (null !== $telephone && !$this->telephoneEstValide($telephone)) {
            $erreurs[] = 'Le numéro de téléphone doit être un numéro français valide, par exemple 06 12 34 56 78.';
        }
        if (null !== $regimeAutre && mb_strlen($regimeAutre) > self::LONGUEUR_TEXTE_LIBRE_MAX) {
            $erreurs[] = 'Le régime ou l’allergie libre est limité à 1 000 caractères.';
        }
        if (null !== $besoinCouchage && mb_strlen($besoinCouchage) > self::LONGUEUR_TEXTE_LIBRE_MAX) {
            $erreurs[] = 'Le besoin de couchage est limité à 1 000 caractères.';
        }

        return [
            'telephone' => $telephone,
            'regime_autre' => $regimeAutre,
            'besoin_couchage' => $besoinCouchage,
            'erreurs' => $erreurs,
        ];
    }

    private function telephoneEstValide(string $telephone): bool
    {
        return 1 === preg_match('/^(?:(?:\+33|0033)[ .-]?[1-9]|0[1-9])(?:[ .-]?\d{2}){4}$/', $telephone);
    }
}
