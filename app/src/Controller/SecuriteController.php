<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecuriteController extends AbstractController
{
    #[Route('/connexion', name: 'app_connexion', methods: ['GET', 'POST'])]
    public function connexion(AuthenticationUtils $authenticationUtils): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_accueil');
        }

        return $this->render('securite/connexion.html.twig', [
            'dernier_identifiant' => $authenticationUtils->getLastUsername(),
            'erreur' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/premiere-connexion/{token}', name: 'app_premiere_connexion', methods: ['GET', 'POST'])]
    public function premiereConnexion(
        string $token,
        Request $request,
        UtilisateurRepository $utilisateurs,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $entityManager,
    ): Response {
        $utilisateur = $utilisateurs->findByActivationToken($token);

        if (null === $utilisateur || !$utilisateur->activationEstValideA(new \DateTimeImmutable())) {
            return $this->redirectToRoute(null !== $this->getUser() ? 'app_accueil' : 'app_connexion');
        }

        $erreurs = [];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('activation-'.$utilisateur->getId(), (string) $request->request->get('_csrf_token'))) {
                $erreurs[] = 'Le formulaire a expiré. Veuillez réessayer.';
            }

            $motDePasse = (string) $request->request->get('mot_de_passe');
            $confirmation = (string) $request->request->get('confirmation');

            if (mb_strlen($motDePasse) < 12) {
                $erreurs[] = 'Le mot de passe doit contenir au moins 12 caractères.';
            }
            if ($motDePasse !== $confirmation) {
                $erreurs[] = 'Les deux mots de passe ne correspondent pas.';
            }

            if ([] === $erreurs) {
                $utilisateur->setPassword($hasher->hashPassword($utilisateur, $motDePasse));
                $utilisateur->terminerActivation();
                $entityManager->flush();
                $this->addFlash('succes', 'Votre mot de passe est créé. Vous pouvez maintenant vous connecter.');

                return $this->redirectToRoute('app_connexion');
            }
        }

        $response = $this->render('securite/premiere_connexion.html.twig', [
            'utilisateur' => $utilisateur,
            'token' => $token,
            'erreurs' => $erreurs,
        ]);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    #[Route('/deconnexion', name: 'app_deconnexion', methods: ['POST'])]
    public function deconnexion(): never
    {
        throw new \LogicException('Cette méthode est interceptée par le pare-feu Symfony.');
    }
}
