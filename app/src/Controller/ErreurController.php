<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ErreurController extends AbstractController
{
    #[Route('/{chemin}', name: 'app_page_introuvable', requirements: ['chemin' => '.+'], methods: ['GET'], priority: -1000)]
    public function pageIntrouvable(): Response
    {
        return $this->redirectToRoute($this->getUser() !== null ? 'app_accueil' : 'app_connexion');
    }
}
