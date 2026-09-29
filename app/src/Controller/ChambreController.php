<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Service\RoomingConfigurationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ChambreController extends AbstractController
{
    #[Route('/administration/chambres', name: 'app_admin_chambres', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, RoomingConfigurationService $rooming): Response
    {
        $this->garantirAccesEquipe();
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            throw $this->createAccessDeniedException();
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('administrer-chambres', $request->request->getString('_csrf_token'))) {
                $this->addFlash('erreur', 'Le formulaire a expiré. Veuillez réessayer.');

                return $this->redirectToRoute('app_admin_chambres');
            }

            try {
                $action = $request->request->getString('action');
                if ('creer_chambre' === $action) {
                    $rooming->creerChambre(
                        $request->request->getString('nom'),
                        $request->request->getInt('capacite'),
                        $request->request->getString('batiment'),
                        $utilisateur,
                    );
                    $this->addFlash('succes', 'La chambre a été créée et est disponible en permanence.');
                } elseif ('ajouter_periode' === $action) {
                    $debut = $this->lireDate($request->request->getString('date_debut'));
                    $fin = $this->lireDate($request->request->getString('date_fin'));
                    if (null === $debut || null === $fin) {
                        throw new \DomainException('Choisissez une période de disponibilité valide.');
                    }
                    $rooming->ajouterPeriodeDisponibilite(
                        $request->request->getString('chambre'),
                        $debut,
                        $fin,
                        $utilisateur,
                    );
                    $this->addFlash('succes', 'La période de disponibilité a été ajoutée. Les périodes contiguës sont regroupées automatiquement.');
                } elseif ('supprimer_periode' === $action) {
                    $rooming->supprimerPeriodeDisponibilite(
                        $request->request->getString('periode'),
                        $utilisateur,
                    );
                    $this->addFlash('succes', 'La période de disponibilité a été supprimée.');
                } elseif ('rendre_permanente' === $action) {
                    $rooming->rendreDisponiblePermanence($request->request->getString('chambre'), $utilisateur);
                    $this->addFlash('succes', 'La chambre est maintenant disponible en permanence.');
                } elseif ('rendre_par_periodes' === $action) {
                    $rooming->rendreDisponibleParPeriodes($request->request->getString('chambre'), $utilisateur);
                    $this->addFlash('succes', 'La chambre est maintenant disponible uniquement pendant les périodes indiquées.');
                } else {
                    throw new \DomainException('Cette action n’est pas reconnue.');
                }
            } catch (\DomainException $exception) {
                $this->addFlash('erreur', $exception->getMessage());
            }

            return $this->redirectToRoute('app_admin_chambres');
        }

        $aujourdhui = new \DateTimeImmutable('today');

        return $this->render('rooming/chambres.html.twig', [
            'chambres' => $rooming->listerConfigurationDisponibilites(),
            'batiments' => $rooming->getBatiments(),
            'date_debut' => $aujourdhui,
            'date_fin' => $aujourdhui->modify('+6 days'),
        ]);
    }

    private function garantirAccesEquipe(): void
    {
        if (!$this->isGranted('ROLE_SALARIE_ACCUEIL') && !$this->isGranted('ROLE_EQUIPE_PILOTE')) {
            throw $this->createAccessDeniedException();
        }
    }

    private function lireDate(string $valeur): ?\DateTimeImmutable
    {
        if (1 !== preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur)) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $valeur);

        return false !== $date && $date->format('Y-m-d') === $valeur ? $date : null;
    }
}
