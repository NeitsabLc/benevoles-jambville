<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class EspacePriveController extends AbstractController
{
    #[Route('/synthese', name: 'app_synthese', methods: ['GET'])]
    public function synthese(): Response
    {
        $this->garantirAccesEquipe();

        return $this->page('Synthèse', 'Accueil et hôtellerie', 'La synthèse privée sera prochainement disponible.');
    }

    #[Route('/administration/calendrier', name: 'app_admin_calendrier', methods: ['GET'])]
    public function calendrier(): Response
    {
        $this->garantirAccesEquipe();

        return $this->page('Configuration du calendrier', 'Administration', 'La gestion des permanences et des journées sera prochainement disponible.');
    }

    #[Route('/administration/thematiques', name: 'app_admin_thematiques', methods: ['GET'])]
    #[IsGranted('ROLE_EQUIPE_PILOTE')]
    public function thematiques(): Response
    {
        return $this->page('Thématiques', 'Administration', 'La gestion des thématiques sera prochainement disponible.');
    }

    #[Route('/administration/benevoles', name: 'app_admin_benevoles', methods: ['GET'])]
    #[IsGranted('ROLE_EQUIPE_PILOTE')]
    public function benevoles(): Response
    {
        return $this->page('Bénévoles', 'Administration', 'La liste des utilisateurs et la gestion des bénévoles seront prochainement disponibles.');
    }

    private function page(string $titre, string $surtitre, string $message): Response
    {
        return $this->render('espace_prive/page_future.html.twig', compact('titre', 'surtitre', 'message'));
    }

    private function garantirAccesEquipe(): void
    {
        if (!$this->isGranted('ROLE_SALARIE_ACCUEIL') && !$this->isGranted('ROLE_EQUIPE_PILOTE')) {
            throw $this->createAccessDeniedException();
        }
    }
}
