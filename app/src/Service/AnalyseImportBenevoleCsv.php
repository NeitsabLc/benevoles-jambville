<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\UtilisateurRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class AnalyseImportBenevoleCsv
{
    private const COLONNES = [
        'code_adherent',
        'nom',
        'prenom',
        'email',
        'telephone',
        'code_fonction',
        'code_structure',
    ];

    private const NOMBRE_MAX_LIGNES = 5_000;

    public function __construct(
        private UtilisateurRepository $utilisateurs,
        private AttributionRoleService $attributionRole,
    ) {
    }

    /** @return array{list<array<string, mixed>>, list<string>} */
    public function analyser(UploadedFile $fichier): array
    {
        $contenu = file_get_contents($fichier->getPathname());
        if (false === $contenu) {
            return [[], ['Le fichier n’a pas pu être lu.']];
        }

        $poignee = fopen('php://temp', 'r+b');
        if (false === $poignee || false === fwrite($poignee, $this->normaliserEncodage($contenu))) {
            return [[], ['Le fichier n’a pas pu être lu.']];
        }
        rewind($poignee);

        $entete = fgetcsv($poignee, 0, ';', '"', '');
        if (false === $entete) {
            fclose($poignee);

            return [[], ['Le fichier est vide.']];
        }
        $entete = array_map(static fn (string $valeur): string => trim($valeur, " \t\n\r\0\x0B\xEF\xBB\xBF"), $entete);
        if (self::COLONNES !== $entete) {
            fclose($poignee);

            return [[], ['L’en-tête du fichier ne correspond pas au format attendu.']];
        }

        $apercu = [];
        $erreurs = [];
        $codesVus = [];
        $emailsVus = [];
        $numeroLigne = 1;
        while (($valeurs = fgetcsv($poignee, 0, ';', '"', '')) !== false) {
            ++$numeroLigne;
            if ($valeurs === [null] || '' === implode('', $valeurs)) {
                continue;
            }
            if (count($valeurs) !== count(self::COLONNES)) {
                $erreurs[] = sprintf('Ligne %d : le nombre de colonnes est incorrect.', $numeroLigne);

                continue;
            }

            /** @var array<string, string> $donnees */
            $donnees = array_combine(self::COLONNES, array_map('trim', $valeurs));
            $erreur = $this->validerLigne($donnees, $codesVus, $emailsVus);
            $codesVus[$donnees['code_adherent']] = true;
            $emailsVus[mb_strtolower($donnees['email'])] = true;

            $existant = $this->utilisateurs->findOneBy(['codeAdherent' => $donnees['code_adherent']]);
            $proprietaireEmail = filter_var($donnees['email'], FILTER_VALIDATE_EMAIL)
                ? $this->utilisateurs->loadUserByIdentifier($donnees['email'])
                : null;
            if (null === $erreur && null !== $proprietaireEmail && $proprietaireEmail->getCodeAdherent() !== $donnees['code_adherent']) {
                $erreur = sprintf('Adresse email déjà utilisée par le code adhérent %s', $proprietaireEmail->getCodeAdherent());
            }

            $attribution = $this->attributionRole->determiner($donnees['code_fonction'], $donnees['code_structure']);
            $modifications = [];
            $statut = 'creation';
            if (null !== $erreur) {
                $statut = 'erreur';
            } elseif (null !== $existant) {
                $telephoneCible = $existant->isTelephoneModifieLocalement()
                    ? (string) $existant->getTelephone()
                    : $donnees['telephone'];
                $comparaisons = [
                    'Nom' => [$existant->getNom(), $donnees['nom']],
                    'Prénom' => [$existant->getPrenom(), $donnees['prenom']],
                    'Email' => [mb_strtolower($existant->getEmail()), mb_strtolower($donnees['email'])],
                    'Téléphone' => [(string) $existant->getTelephone(), $telephoneCible],
                    'Code fonction' => [(string) $existant->getCodeFonction(), $donnees['code_fonction']],
                    'Code structure' => [(string) $existant->getCodeStructure(), $donnees['code_structure']],
                    'Rôle' => [$existant->getRoleMetier(), $attribution['role']],
                ];
                foreach ($comparaisons as $champ => [$avant, $apres]) {
                    if ($avant !== $apres) {
                        $modifications[] = ['champ' => $champ, 'avant' => $avant, 'apres' => $apres];
                    }
                }
                if (!$existant->isActif()) {
                    $modifications[] = ['champ' => 'Compte', 'avant' => 'Inactif', 'apres' => 'Actif'];
                }
                $statut = [] === $modifications ? 'inchange' : 'mise_a_jour';
            }

            $apercu[] = $donnees + [
                'ligne' => $numeroLigne,
                'statut' => $statut,
                'erreur' => $erreur,
                'role_cible' => $attribution['role'],
                'regle_role_reconnue' => $attribution['regle_reconnue'],
                'modifications' => $modifications,
            ];
            if (count($apercu) > self::NOMBRE_MAX_LIGNES) {
                $erreurs[] = 'Le fichier ne peut pas contenir plus de 5 000 bénévoles.';

                break;
            }
        }
        fclose($poignee);

        if ([] === $apercu && [] === $erreurs) {
            $erreurs[] = 'Le fichier ne contient aucun bénévole.';
        }

        return [$apercu, $erreurs];
    }

    /**
     * @param array<string, string> $donnees
     * @param array<string, true>   $codesVus
     * @param array<string, true>   $emailsVus
     */
    private function validerLigne(array $donnees, array $codesVus, array $emailsVus): ?string
    {
        if ('' === $donnees['code_adherent'] || '' === $donnees['nom'] || '' === $donnees['prenom'] || '' === $donnees['email']) {
            return 'Champs obligatoires manquants';
        }
        if (mb_strlen($donnees['code_adherent']) > 50 || mb_strlen($donnees['nom']) > 100 || mb_strlen($donnees['prenom']) > 100 || mb_strlen($donnees['email']) > 180 || mb_strlen($donnees['telephone']) > 30 || mb_strlen($donnees['code_fonction']) > 50 || mb_strlen($donnees['code_structure']) > 50) {
            return 'Un champ dépasse la longueur maximale autorisée';
        }
        if (!filter_var($donnees['email'], FILTER_VALIDATE_EMAIL)) {
            return 'Adresse email invalide';
        }
        if (isset($codesVus[$donnees['code_adherent']])) {
            return 'Code adhérent en doublon';
        }
        if (isset($emailsVus[mb_strtolower($donnees['email'])])) {
            return 'Adresse email en doublon dans le fichier';
        }

        return null;
    }

    private function normaliserEncodage(string $contenu): string
    {
        if (str_starts_with($contenu, "\xEF\xBB\xBF")) {
            return substr($contenu, 3);
        }
        if (mb_check_encoding($contenu, 'UTF-8')) {
            return $contenu;
        }

        if (1 === preg_match('/[A-Z]\xE8[A-Z]/', $contenu)) {
            $contenuMac = iconv('MACINTOSH', 'UTF-8', $contenu);
            if (false !== $contenuMac) {
                return $contenuMac;
            }
        }

        return mb_convert_encoding($contenu, 'UTF-8', 'Windows-1252');
    }
}
