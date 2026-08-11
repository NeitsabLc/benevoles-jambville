<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use App\Service\AttributionRoleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/administration/benevoles')]
#[IsGranted('ROLE_EQUIPE_PILOTE')]
final class BenevoleController extends AbstractController
{
    #[Route('', name: 'app_admin_benevoles', methods: ['GET'])]
    public function index(UtilisateurRepository $utilisateurs): Response
    {
        return $this->render('benevole/index.html.twig', [
            'benevoles' => $utilisateurs->findTousPourAdministration(),
        ]);
    }

    #[Route('/ajouter', name: 'app_admin_benevole_ajouter', methods: ['GET', 'POST'])]
    public function ajouter(
        Request $request,
        Connection $connexion,
        MailerInterface $mailer,
        #[Autowire('%env(MAILER_FROM)%')] string $adresseExpediteur,
        #[Autowire('%kernel.project_dir%')] string $repertoireProjet,
    ): Response {
        $valeurs = [
            'code_adherent' => trim($request->request->getString('code_adherent')),
            'nom' => trim($request->request->getString('nom')),
            'prenom' => trim($request->request->getString('prenom')),
            'email' => mb_strtolower(trim($request->request->getString('email'))),
            'telephone' => trim($request->request->getString('telephone')),
            'role' => $request->request->getString('role', 'BENEVOLE'),
        ];
        $erreurs = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('ajouter-benevole', $request->request->getString('_csrf_token'))) {
                $erreurs[] = 'Le formulaire a expiré. Veuillez réessayer.';
            }
            if ($valeurs['code_adherent'] === '' || $valeurs['nom'] === '' || $valeurs['prenom'] === '' || $valeurs['email'] === '') {
                $erreurs[] = 'Renseignez tous les champs obligatoires.';
            }
            if (mb_strlen($valeurs['code_adherent']) > 50 || mb_strlen($valeurs['nom']) > 100 || mb_strlen($valeurs['prenom']) > 100 || mb_strlen($valeurs['email']) > 180 || mb_strlen($valeurs['telephone']) > 30) {
                $erreurs[] = 'Un champ dépasse la longueur maximale autorisée.';
            }
            if ($valeurs['email'] !== '' && !filter_var($valeurs['email'], FILTER_VALIDATE_EMAIL)) {
                $erreurs[] = 'L’adresse email n’est pas valide.';
            }
            if (!in_array($valeurs['role'], ['BENEVOLE', 'SALARIE_ACCUEIL', 'EQUIPE_PILOTE'], true)) {
                $erreurs[] = 'Choisissez un rôle valide.';
            }

            if ($erreurs === []) {
                $token = bin2hex(random_bytes(32));
                try {
                    $connexion->insert('benevole_jambville.utilisateur', [
                        'code_adherent' => $valeurs['code_adherent'],
                        'nom' => $valeurs['nom'],
                        'prenom' => $valeurs['prenom'],
                        'email' => $valeurs['email'],
                        'telephone' => $valeurs['telephone'] !== '' ? $valeurs['telephone'] : null,
                        'role' => $valeurs['role'],
                        'source_role' => 'MANUEL',
                        'changement_mot_de_passe_requis' => true,
                        'informations_accueil_completees' => $valeurs['role'] === 'SALARIE_ACCUEIL' ? 1 : 0,
                        'jeton_activation' => hash('sha256', $token),
                        'expiration_jeton_activation' => (new \DateTimeImmutable('+7 days'))->format('Y-m-d H:i:sO'),
                    ]);
                } catch (UniqueConstraintViolationException) {
                    $erreurs[] = 'Un compte utilise déjà ce code adhérent ou cette adresse email.';
                }

                if ($erreurs === []) {
                    $url = $this->generateUrl('app_premiere_connexion', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
                    try {
                        $mailer->send((new TemplatedEmail())
                            ->from(new Address($adresseExpediteur, 'Bénévoles Jambville'))
                            ->to($valeurs['email'])
                            ->subject('Bienvenue sur Bénévoles Jambville')
                            ->htmlTemplate('email/premiere_connexion.html.twig')
                            ->context(['nom' => $valeurs['prenom'].' '.$valeurs['nom'], 'urlActivation' => $url])
                            ->embedFromPath($repertoireProjet.'/assets/images/sgdf-logo-horizontal.png', 'sgdf-logo-horizontal')
                            ->text("Bonjour {$valeurs['prenom']} {$valeurs['nom']},\n\nVotre compte Bénévoles Jambville vient d’être créé. Choisissez votre mot de passe dans les 7 jours :\n{$url}"));
                        $this->addFlash('succes', sprintf('Le compte de %s %s a été créé. Son invitation a été envoyée.', $valeurs['prenom'], $valeurs['nom']));
                    } catch (TransportExceptionInterface) {
                        $this->addFlash('erreur', sprintf('Le compte de %s %s a été créé, mais l’invitation n’a pas pu être envoyée.', $valeurs['prenom'], $valeurs['nom']));
                    }

                    return $this->redirectToRoute('app_admin_benevoles');
                }
            }
        }

        return $this->render(
            'benevole/ajouter.html.twig',
            ['valeurs' => $valeurs, 'erreurs' => $erreurs],
            $request->isMethod('POST') ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY) : null,
        );
    }

    #[Route('/{id}/activation', name: 'app_admin_benevole_activation', methods: ['POST'])]
    public function activation(Utilisateur $utilisateur, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('activation-benevole-'.$utilisateur->getId(), $request->request->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('Le formulaire a expiré.');
        }

        if ($utilisateur === $this->getUser()) {
            $this->addFlash('erreur', 'Vous ne pouvez pas désactiver votre propre compte.');

            return $this->redirectToRoute('app_admin_benevoles');
        }

        $utilisateur->basculerActivation();
        $entityManager->flush();
        $this->addFlash('succes', sprintf(
            'Le compte de %s a été %s.',
            $utilisateur->getNomComplet(),
            $utilisateur->isActif() ? 'réactivé' : 'désactivé',
        ));

        return $this->redirectToRoute('app_admin_benevoles');
    }

    #[Route('/importer', name: 'app_admin_benevoles_importer', methods: ['GET', 'POST'])]
    public function importer(
        Request $request,
        UtilisateurRepository $utilisateurs,
        AttributionRoleService $attributionRole,
    ): Response
    {
        $erreurs = [];
        $apercu = [];
        $fichier = $request->files->get('fichier_csv');

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('import-benevoles', $request->request->getString('_csrf_token'))) {
                $erreurs[] = 'Le formulaire a expiré. Veuillez réessayer.';
            } elseif (!$fichier instanceof UploadedFile || !$fichier->isValid()) {
                $erreurs[] = 'Sélectionnez un fichier CSV valide.';
            } elseif ($fichier->getSize() > 2_000_000) {
                $erreurs[] = 'Le fichier ne doit pas dépasser 2 Mo.';
            } else {
                [$apercu, $erreurs] = $this->analyserCsv($fichier, $utilisateurs, $attributionRole);
                if ($erreurs === [] && !array_filter($apercu, static fn (array $ligne): bool => $ligne['statut'] === 'erreur')) {
                    $request->getSession()->set('import_benevoles_apercu', $apercu);
                } else {
                    $request->getSession()->remove('import_benevoles_apercu');
                }
            }
        }

        return $this->render('benevole/importer.html.twig', [
            'erreurs' => $erreurs,
            'apercu' => $apercu,
            'peutAppliquer' => $apercu !== [] && $erreurs === [] && !array_filter($apercu, static fn (array $ligne): bool => $ligne['statut'] === 'erreur'),
            'resultatImport' => $request->getSession()->remove('resultat_import_benevoles'),
        ]);
    }

    #[Route('/importer/appliquer', name: 'app_admin_benevoles_importer_appliquer', methods: ['POST'])]
    public function appliquerImport(
        Request $request,
        Connection $connexion,
        AttributionRoleService $attributionRole,
        MailerInterface $mailer,
        #[Autowire('%env(MAILER_FROM)%')] string $adresseExpediteur,
        #[Autowire('%kernel.project_dir%')] string $repertoireProjet,
    ): Response
    {
        if (!$this->isCsrfTokenValid('appliquer-import-benevoles', $request->request->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('Le formulaire a expiré.');
        }
        $apercu = $request->getSession()->get('import_benevoles_apercu');
        if (!is_array($apercu) || $apercu === []) {
            $this->addFlash('erreur', 'La prévisualisation a expiré. Importez à nouveau le fichier.');
            return $this->redirectToRoute('app_admin_benevoles_importer');
        }

        $liensActivation = [];
        $creations = 0;
        $misesAJour = 0;
        try {
            $connexion->transactional(function (Connection $connexion) use ($apercu, $attributionRole, &$liensActivation, &$creations, &$misesAJour): void {
                foreach ($apercu as $ligne) {
                    if (!is_array($ligne) || ($ligne['statut'] ?? 'erreur') === 'erreur') {
                        throw new \RuntimeException('Une ligne invalide empêche l’import.');
                    }
                    $attribution = $attributionRole->determiner($ligne['code_fonction'], $ligne['code_structure']);
                    $role = $attribution['role'];
                    $version = $attribution['version'];

                    $existe = (bool) $connexion->fetchOne('SELECT EXISTS(SELECT 1 FROM benevole_jambville.utilisateur WHERE code_adherent = :code)', ['code' => $ligne['code_adherent']]);
                    $parametres = [
                        'code' => $ligne['code_adherent'], 'nom' => $ligne['nom'], 'prenom' => $ligne['prenom'],
                        'email' => mb_strtolower($ligne['email']), 'telephone' => $ligne['telephone'] !== '' ? $ligne['telephone'] : null,
                        'fonction' => $ligne['code_fonction'] !== '' ? $ligne['code_fonction'] : null,
                        'structure' => $ligne['code_structure'] !== '' ? $ligne['code_structure'] : null,
                        'role' => $role, 'version' => $version,
                    ];
                    if ($existe) {
                        $connexion->executeStatement('UPDATE benevole_jambville.utilisateur SET nom = :nom, prenom = :prenom, email = :email, telephone = CASE WHEN telephone_modifie_localement THEN telephone ELSE :telephone END, code_fonction = :fonction, code_structure = :structure, role = :role, source_role = \'CSV\', role_calcule_le = CURRENT_TIMESTAMP, version_regle_role = :version, actif = TRUE, modifie_le = CURRENT_TIMESTAMP WHERE code_adherent = :code', $parametres);
                        if ($ligne['statut'] === 'mise_a_jour') { ++$misesAJour; }
                    } else {
                        $token = bin2hex(random_bytes(32));
                        $parametres['jeton'] = hash('sha256', $token);
                        $parametres['expiration'] = (new \DateTimeImmutable('+7 days'))->format('Y-m-d H:i:sO');
                        $parametres['informations_accueil_completees'] = $role === 'SALARIE_ACCUEIL' ? 1 : 0;
                        $connexion->executeStatement('INSERT INTO benevole_jambville.utilisateur (code_adherent, nom, prenom, email, telephone, code_fonction, code_structure, role, source_role, role_calcule_le, version_regle_role, changement_mot_de_passe_requis, informations_accueil_completees, jeton_activation, expiration_jeton_activation) VALUES (:code, :nom, :prenom, :email, :telephone, :fonction, :structure, :role, \'CSV\', CURRENT_TIMESTAMP, :version, TRUE, :informations_accueil_completees, :jeton, :expiration)', $parametres);
                        $liensActivation[] = ['nom' => $ligne['prenom'].' '.$ligne['nom'], 'email' => $ligne['email'], 'url' => $this->generateUrl('app_premiere_connexion', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL)];
                        ++$creations;
                    }
                }
            });
        } catch (UniqueConstraintViolationException) {
            $this->addFlash('erreur', 'L’import n’a pas été appliqué : une adresse email appartient déjà à un autre compte.');
            return $this->redirectToRoute('app_admin_benevoles_importer');
        }

        $echecsEmail = 0;
        foreach ($liensActivation as $lien) {
            try {
                $mailer->send((new TemplatedEmail())
                    ->from(new Address($adresseExpediteur, 'Bénévoles Jambville'))
                    ->to($lien['email'])
                    ->subject('Bienvenue sur Bénévoles Jambville')
                    ->htmlTemplate('email/premiere_connexion.html.twig')
                    ->context(['nom' => $lien['nom'], 'urlActivation' => $lien['url']])
                    ->embedFromPath($repertoireProjet.'/assets/images/sgdf-logo-horizontal.png', 'sgdf-logo-horizontal')
                    ->text("Bonjour {$lien['nom']},\n\nBienvenue sur Bénévoles Jambville !\n\nVotre compte vient d’être créé. Choisissez votre mot de passe dans les 7 jours en utilisant ce lien :\n{$lien['url']}\n\nÀ bientôt à Jambville,\nL’équipe Bénévoles Jambville"));
            } catch (TransportExceptionInterface) {
                ++$echecsEmail;
            }
        }

        $csvLiens = "nom;email;lien_premiere_connexion\r\n";
        foreach ($liensActivation as $lien) {
            $csvLiens .= implode(';', array_map($this->encoderCelluleCsv(...), [$lien['nom'], $lien['email'], $lien['url']]))."\r\n";
        }
        $request->getSession()->remove('import_benevoles_apercu');
        $request->getSession()->set('fichier_liens_import_benevoles', "\xEF\xBB\xBF".$csvLiens);
        $request->getSession()->set('resultat_import_benevoles', ['creations' => $creations, 'mises_a_jour' => $misesAJour, 'nombre_liens' => count($liensActivation), 'echecs_email' => $echecsEmail]);
        return $this->redirectToRoute('app_admin_benevoles_importer');
    }

    #[Route('/importer/liens', name: 'app_admin_benevoles_importer_liens', methods: ['GET'])]
    public function telechargerLiens(Request $request): Response
    {
        $contenu = $request->getSession()->remove('fichier_liens_import_benevoles');
        if (!is_string($contenu)) {
            throw $this->createNotFoundException('Le fichier de liens n’est plus disponible.');
        }

        return new Response($contenu, Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="liens-premiere-connexion.csv"',
        ]);
    }

    /** @return array{list<array<string, mixed>>, list<string>} */
    private function analyserCsv(
        UploadedFile $fichier,
        UtilisateurRepository $utilisateurs,
        AttributionRoleService $attributionRole,
    ): array
    {
        $colonnes = ['code_adherent', 'nom', 'prenom', 'email', 'telephone', 'code_fonction', 'code_structure'];
        $contenu = file_get_contents($fichier->getPathname());
        if ($contenu === false) {
            return [[], ['Le fichier n’a pas pu être lu.']];
        }
        $contenu = $this->normaliserEncodageCsv($contenu);
        $poignee = fopen('php://temp', 'r+b');
        if ($poignee === false || fwrite($poignee, $contenu) === false) {
            return [[], ['Le fichier n’a pas pu être lu.']];
        }
        rewind($poignee);

        $entete = fgetcsv($poignee, 0, ';', '"', '');
        if ($entete === false) {
            fclose($poignee);
            return [[], ['Le fichier est vide.']];
        }
        $entete = array_map(static fn (string $valeur): string => trim($valeur, " \t\n\r\0\x0B\xEF\xBB\xBF"), $entete);
        if ($entete !== $colonnes) {
            fclose($poignee);
            return [[], ['L’en-tête du fichier ne correspond pas au format attendu.']];
        }

        $apercu = [];
        $erreurs = [];
        $codesVus = [];
        $emailsVus = [];
        $ligne = 1;
        while (($valeurs = fgetcsv($poignee, 0, ';', '"', '')) !== false) {
            ++$ligne;
            if ($valeurs === [null] || implode('', $valeurs) === '') {
                continue;
            }
            if (count($valeurs) !== count($colonnes)) {
                $erreurs[] = sprintf('Ligne %d : le nombre de colonnes est incorrect.', $ligne);
                continue;
            }
            $donnees = array_combine($colonnes, array_map('trim', $valeurs));
            $erreur = null;
            if ($donnees['code_adherent'] === '' || $donnees['nom'] === '' || $donnees['prenom'] === '' || $donnees['email'] === '') {
                $erreur = 'Champs obligatoires manquants';
            } elseif (mb_strlen($donnees['code_adherent']) > 50 || mb_strlen($donnees['nom']) > 100 || mb_strlen($donnees['prenom']) > 100 || mb_strlen($donnees['email']) > 180 || mb_strlen($donnees['telephone']) > 30 || mb_strlen($donnees['code_fonction']) > 50 || mb_strlen($donnees['code_structure']) > 50) {
                $erreur = 'Un champ dépasse la longueur maximale autorisée';
            } elseif (!filter_var($donnees['email'], FILTER_VALIDATE_EMAIL)) {
                $erreur = 'Adresse email invalide';
            } elseif (isset($codesVus[$donnees['code_adherent']])) {
                $erreur = 'Code adhérent en doublon';
            } elseif (isset($emailsVus[mb_strtolower($donnees['email'])])) {
                $erreur = 'Adresse email en doublon dans le fichier';
            }
            $codesVus[$donnees['code_adherent']] = true;
            $emailsVus[mb_strtolower($donnees['email'])] = true;
            $existant = $utilisateurs->findOneBy(['codeAdherent' => $donnees['code_adherent']]);
            $proprietaireEmail = filter_var($donnees['email'], FILTER_VALIDATE_EMAIL)
                ? $utilisateurs->loadUserByIdentifier($donnees['email'])
                : null;
            if ($erreur === null && $proprietaireEmail !== null && $proprietaireEmail->getCodeAdherent() !== $donnees['code_adherent']) {
                $erreur = sprintf('Adresse email déjà utilisée par le code adhérent %s', $proprietaireEmail->getCodeAdherent());
            }
            $attribution = $attributionRole->determiner($donnees['code_fonction'], $donnees['code_structure']);
            $modifications = [];
            $statut = 'creation';
            if ($erreur !== null) {
                $statut = 'erreur';
            } elseif ($existant !== null) {
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
                $statut = $modifications === [] ? 'inchange' : 'mise_a_jour';
            }
            $apercu[] = $donnees + [
                'ligne' => $ligne,
                'statut' => $statut,
                'erreur' => $erreur,
                'role_cible' => $attribution['role'],
                'regle_role_reconnue' => $attribution['regle_reconnue'],
                'modifications' => $modifications,
            ];
            if (count($apercu) > 5000) {
                $erreurs[] = 'Le fichier ne peut pas contenir plus de 5 000 bénévoles.';
                break;
            }
        }
        fclose($poignee);

        if ($apercu === [] && $erreurs === []) {
            $erreurs[] = 'Le fichier ne contient aucun bénévole.';
        }

        return [$apercu, $erreurs];
    }

    private function normaliserEncodageCsv(string $contenu): string
    {
        if (str_starts_with($contenu, "\xEF\xBB\xBF")) {
            return substr($contenu, 3);
        }
        if (mb_check_encoding($contenu, 'UTF-8')) {
            return $contenu;
        }

        // Dans Mac Roman, l'octet E8 représente « Ë » alors qu'il représente
        // « è » en Windows-1252. Entouré de majuscules dans un nom (CAËR), ce
        // motif permet d'identifier sans ambiguïté les exports Excel sur Mac.
        if (preg_match('/[A-Z]\xE8[A-Z]/', $contenu) === 1) {
            $contenuMac = iconv('MACINTOSH', 'UTF-8', $contenu);
            if ($contenuMac !== false) {
                return $contenuMac;
            }
        }

        // Les exports CSV d'Excel en français utilisent généralement Windows-1252.
        return mb_convert_encoding($contenu, 'UTF-8', 'Windows-1252');
    }

    private function encoderCelluleCsv(string $valeur): string
    {
        if (preg_match('/^[=+\-@]/', ltrim($valeur)) === 1) {
            $valeur = "'".$valeur;
        }

        return '"'.str_replace('"', '""', $valeur).'"';
    }
}
