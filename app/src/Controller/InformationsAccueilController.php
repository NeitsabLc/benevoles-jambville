<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class InformationsAccueilController extends AbstractController
{
    #[Route('/bienvenue/informations-pratiques', name: 'app_informations_accueil', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, EntityManagerInterface $entityManager): Response
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur || !$utilisateur->doitCompleterInformationsAccueil()) {
            return $this->redirectToRoute('app_accueil');
        }

        $erreurs = [];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('informations-accueil', $request->request->getString('_csrf_token'))) {
                $erreurs[] = 'Le formulaire a expiré. Veuillez réessayer.';
            }

            if ($erreurs === []) {
                $utilisateur->modifierProfil(
                    $utilisateur->getTelephone(),
                    $request->request->getBoolean('vegetarien'),
                    $request->request->getBoolean('allergie_oeuf'),
                    $request->request->getBoolean('allergie_arachide'),
                    trim($request->request->getString('regime_autre')) ?: null,
                    trim($request->request->getString('besoin_couchage')) ?: null,
                );
                $utilisateur->terminerInformationsAccueil();
                $entityManager->flush();
                $this->addFlash('succes', 'Merci, vos informations pratiques ont bien été enregistrées.');

                return $this->redirectToRoute('app_accueil');
            }
        }

        return $this->render('profil/informations_accueil.html.twig', ['erreurs' => $erreurs]);
    }
}
