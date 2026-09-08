<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

final readonly class AttributionRoleService
{
    public function __construct(private Connection $connexion)
    {
    }

    /** @return array{role: string, version: ?string, regle_reconnue: bool} */
    public function determiner(string $codeFonction, string $codeStructure): array
    {
        $regle = $this->connexion->fetchAssociative(
            'SELECT role_attribue, version FROM benevole_jambville.regle_attribution_role WHERE actif = TRUE AND code_fonction = :fonction AND code_structure = :structure',
            ['fonction' => $codeFonction, 'structure' => $codeStructure],
        );

        return [
            'role' => false !== $regle ? (string) $regle['role_attribue'] : 'BENEVOLE',
            'version' => false !== $regle ? (string) $regle['version'] : null,
            'regle_reconnue' => false !== $regle,
        ];
    }
}
