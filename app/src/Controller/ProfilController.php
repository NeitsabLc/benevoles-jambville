<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Service\ValidationProfilService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ProfilController extends AbstractController
{
    #[Route('/mon-profil', name: 'app_profil', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $hasher,
        ValidationProfilService $validationProfil,
    ): Response {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            throw $this->createAccessDeniedException();
        }

        $erreurs = [];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('modifier-profil', $request->request->getString('_csrf_token'))) {
                $erreurs[] = 'Le formulaire a expiré. Veuillez réessayer.';
            }

            $profil = $validationProfil->valider(
                $request->request->getString('telephone'),
                $request->request->getString('regime_autre'),
                $request->request->getString('besoin_couchage'),
            );
            $erreurs = [...$erreurs, ...$profil['erreurs']];

            $motDePasseActuel = $request->request->getString('mot_de_passe_actuel');
            $nouveauMotDePasse = $request->request->getString('nouveau_mot_de_passe');
            $confirmationMotDePasse = $request->request->getString('confirmation_mot_de_passe');
            $modificationMotDePasseDemandee = '' !== $motDePasseActuel || '' !== $nouveauMotDePasse || '' !== $confirmationMotDePasse;
            if ($modificationMotDePasseDemandee) {
                if (!$hasher->isPasswordValid($utilisateur, $motDePasseActuel)) {
                    $erreurs[] = 'Le mot de passe actuel est incorrect.';
                }
                if (mb_strlen($nouveauMotDePasse) < 12) {
                    $erreurs[] = 'Le nouveau mot de passe doit contenir au moins 12 caractères.';
                }
                if ($nouveauMotDePasse !== $confirmationMotDePasse) {
                    $erreurs[] = 'Les deux nouveaux mots de passe ne correspondent pas.';
                }
            }

            if ([] === $erreurs) {
                $utilisateur->modifierProfil(
                    $profil['telephone'],
                    $request->request->getBoolean('vegetarien'),
                    $request->request->getBoolean('allergie_oeuf'),
                    $request->request->getBoolean('allergie_arachide'),
                    $profil['regime_autre'],
                    $profil['besoin_couchage'],
                );
                if ($utilisateur->isEquipePilote()) {
                    $utilisateur->modifierRemiseEquipement(
                        $request->request->getBoolean('foulard_remis'),
                        $request->request->getBoolean('tenue_remise'),
                    );
                }
                if ($modificationMotDePasseDemandee) {
                    $utilisateur->setPassword($hasher->hashPassword($utilisateur, $nouveauMotDePasse));
                }
                $entityManager->flush();
                $this->addFlash('succes', 'Votre profil a bien été mis à jour.');

                return $this->redirectToRoute('app_profil');
            }
        }

        return $this->render('profil/index.html.twig', [
            'erreurs' => $erreurs,
            'utilisateurProfil' => $utilisateur,
            'profilAdmin' => false,
            'actionProfil' => $this->generateUrl('app_profil'),
        ]);
    }

    #[Route('/administration/benevoles/{id}/profil', name: 'app_admin_benevole_profil', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_EQUIPE_PILOTE')]
    public function administrer(
        Utilisateur $utilisateur,
        Request $request,
        EntityManagerInterface $entityManager,
        ValidationProfilService $validationProfil,
    ): Response {
        $erreurs = [];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('modifier-profil-'.$utilisateur->getId(), $request->request->getString('_csrf_token'))) {
                $erreurs[] = 'Le formulaire a expiré. Veuillez réessayer.';
            }

            $role = $request->request->getString('role');
            $profil = $validationProfil->valider(
                $request->request->getString('telephone'),
                $request->request->getString('regime_autre'),
                $request->request->getString('besoin_couchage'),
            );
            $erreurs = [...$erreurs, ...$profil['erreurs']];
            if (!in_array($role, ['BENEVOLE', 'EQUIPE_PILOTE', 'SALARIE_ACCUEIL'], true)) {
                $erreurs[] = 'Choisissez un rôle valide.';
            }

            if ([] === $erreurs) {
                $utilisateur->modifierProfil(
                    $profil['telephone'],
                    $request->request->getBoolean('vegetarien'),
                    $request->request->getBoolean('allergie_oeuf'),
                    $request->request->getBoolean('allergie_arachide'),
                    $profil['regime_autre'],
                    $profil['besoin_couchage'],
                );
                $utilisateur->modifierRemiseEquipement(
                    $request->request->getBoolean('foulard_remis'),
                    $request->request->getBoolean('tenue_remise'),
                );
                $utilisateur->modifierRole($role);
                $entityManager->flush();
                $this->addFlash('succes', 'Le profil a bien été mis à jour.');

                return $this->redirectToRoute('app_admin_benevole_profil', ['id' => $utilisateur->getId()]);
            }
        }

        return $this->render('profil/index.html.twig', [
            'erreurs' => $erreurs,
            'utilisateurProfil' => $utilisateur,
            'profilAdmin' => true,
            'actionProfil' => $this->generateUrl('app_admin_benevole_profil', ['id' => $utilisateur->getId()]),
        ]);
    }
}
