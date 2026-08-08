<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ProfilController extends AbstractController
{
    #[Route('/mon-profil', name: 'app_profil', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $hasher,
    ): Response
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            throw $this->createAccessDeniedException();
        }

        $erreurs = [];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('modifier-profil', $request->request->getString('_csrf_token'))) {
                $erreurs[] = 'Le formulaire a expiré. Veuillez réessayer.';
            }

            $telephone = trim($request->request->getString('telephone')) ?: null;
            $regimeAutre = trim($request->request->getString('regime_autre')) ?: null;
            $besoinCouchage = trim($request->request->getString('besoin_couchage')) ?: null;
            if ($telephone !== null && mb_strlen($telephone) > 30) {
                $erreurs[] = 'Le numéro de téléphone ne peut pas dépasser 30 caractères.';
            } elseif ($telephone !== null && !$this->telephoneEstValide($telephone)) {
                $erreurs[] = 'Le numéro de téléphone doit être un numéro français valide, par exemple 06 12 34 56 78.';
            }

            $motDePasseActuel = $request->request->getString('mot_de_passe_actuel');
            $nouveauMotDePasse = $request->request->getString('nouveau_mot_de_passe');
            $confirmationMotDePasse = $request->request->getString('confirmation_mot_de_passe');
            $modificationMotDePasseDemandee = $motDePasseActuel !== '' || $nouveauMotDePasse !== '' || $confirmationMotDePasse !== '';
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

            if ($erreurs === []) {
                $utilisateur->modifierProfil(
                    $telephone,
                    $request->request->getBoolean('vegetarien'),
                    $request->request->getBoolean('allergie_oeuf'),
                    $request->request->getBoolean('allergie_arachide'),
                    $regimeAutre,
                    $besoinCouchage,
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

        return $this->render('profil/index.html.twig', ['erreurs' => $erreurs]);
    }

    private function telephoneEstValide(string $telephone): bool
    {
        return preg_match('/^(?:(?:\+33|0033)[ .-]?[1-9]|0[1-9])(?:[ .-]?\d{2}){4}$/', $telephone) === 1;
    }
}
