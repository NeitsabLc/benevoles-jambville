<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Inscription;
use App\Repository\InscriptionRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class EspacePriveController extends AbstractController
{
    #[Route('/synthese', name: 'app_synthese', methods: ['GET'])]
    public function synthese(Request $request, InscriptionRepository $inscriptions): Response
    {
        $this->garantirAccesEquipe();

        $aujourdhui = new \DateTimeImmutable('today');
        $debut = $this->lireDate($request->query->getString('debut')) ?? $aujourdhui;
        $fin = $this->lireDate($request->query->getString('fin')) ?? $debut->modify('+6 days');
        $erreur = null;

        if ($fin < $debut) {
            $erreur = 'La date de fin doit être postérieure ou égale à la date de début.';
            $fin = $debut;
        } elseif ($fin > $debut->modify('+62 days')) {
            $erreur = 'La période affichée est limitée à 63 jours.';
            $fin = $debut->modify('+62 days');
        }

        $jours = [];
        for ($date = $debut; $date <= $fin; $date = $date->modify('+1 day')) {
            $jours[$date->format('Y-m-d')] = [
                'date' => $date,
                'libelle_date' => $this->libelleDate($date),
                'repas' => ['PETIT_DEJEUNER' => 0, 'DEJEUNER' => 0, 'DINER' => 0],
                'presences' => [],
                'couchages' => ['DUR' => ['total' => 0, 'presences' => []], 'TENTE' => ['total' => 0, 'presences' => []]],
                'regimes' => ['vegetariens' => 0, 'oeuf' => 0, 'arachide' => 0, 'commentaires' => []],
            ];
        }

        foreach ($inscriptions->findPourSynthese($debut, $fin) as $inscription) {
            $effectif = $this->effectif($inscription);
            $libelle = $this->libellePresence($inscription);
            $premierJour = max($debut, $inscription->getDateDebut());
            $dernierJour = min($fin, $inscription->getDateFin());

            for ($date = $premierJour; $date <= $dernierJour; $date = $date->modify('+1 day')) {
                $cle = $date->format('Y-m-d');
                $presence = ['libelle' => $libelle, 'effectif' => $effectif, 'est_equipe' => $inscription->getType() === 'COMPAGNON'];
                $jours[$cle]['presences'][] = $presence;
                $jours[$cle]['couchages'][$inscription->getTypeCouchage()]['total'] += $effectif;
                $jours[$cle]['couchages'][$inscription->getTypeCouchage()]['presences'][] = $presence;

                if ($inscription->getType() === 'COMPAGNON') {
                    $jours[$cle]['regimes']['vegetariens'] += $inscription->getNombreVegetariens();
                    $jours[$cle]['regimes']['oeuf'] += $inscription->getNombreAllergieOeuf();
                    $jours[$cle]['regimes']['arachide'] += $inscription->getNombreAllergieArachide();
                } else {
                    $utilisateur = $inscription->getUtilisateur();
                    $jours[$cle]['regimes']['vegetariens'] += (int) $utilisateur?->isVegetarien();
                    $jours[$cle]['regimes']['oeuf'] += (int) $utilisateur?->hasAllergieOeuf();
                    $jours[$cle]['regimes']['arachide'] += (int) $utilisateur?->hasAllergieArachide();
                    $regimeAutre = trim((string) $utilisateur?->getRegimeAutre());
                    if ($regimeAutre !== '') {
                        if (!in_array($regimeAutre, $jours[$cle]['regimes']['commentaires'], true)) {
                            $jours[$cle]['regimes']['commentaires'][] = $regimeAutre;
                        }
                    }
                }
            }

            foreach ($inscription->getRepas() as $repas) {
                $cle = $repas->getDateRepas()->format('Y-m-d');
                if ($repas->isSelectionne() && isset($jours[$cle])) {
                    $jours[$cle]['repas'][$repas->getTypeRepas()] += $effectif;
                }
            }
        }

        return $this->render('espace_prive/synthese.html.twig', [
            'jours' => array_values($jours),
            'debut' => $debut,
            'fin' => $fin,
            'erreur' => $erreur,
        ]);
    }

    #[Route('/administration/calendrier', name: 'app_admin_calendrier', methods: ['GET'])]
    public function calendrier(): Response
    {
        $this->garantirAccesEquipe();

        return $this->page('Configuration du calendrier', 'Administration', 'La gestion des permanences et des journées sera prochainement disponible.');
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

    private function lireDate(string $valeur): ?\DateTimeImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur) !== 1) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $valeur);

        return $date !== false && $date->format('Y-m-d') === $valeur ? $date : null;
    }

    private function effectif(Inscription $inscription): int
    {
        return $inscription->getType() === 'COMPAGNON'
            ? (int) $inscription->getNombrePersonnes()
            : 1 + $inscription->getNombreEnfants();
    }

    private function libellePresence(Inscription $inscription): ?string
    {
        if ($inscription->getType() === 'COMPAGNON') {
            return $inscription->getNomEquipeCompa();
        }

        $utilisateur = $inscription->getUtilisateur();
        if ($utilisateur === null) {
            return null;
        }

        $libelle = $utilisateur->getPrenom().' '.mb_strtoupper(mb_substr($utilisateur->getNom(), 0, 1)).'.';
        $nombreEnfants = $inscription->getNombreEnfants();
        if ($nombreEnfants > 0) {
            $libelle .= ' + '.$nombreEnfants.' enfant'.($nombreEnfants > 1 ? 's' : '');
        }

        return $libelle;
    }

    private function libelleDate(\DateTimeImmutable $date): string
    {
        $jours = [1 => 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
        $mois = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

        return $jours[(int) $date->format('N')].' '.$date->format('j').' '.$mois[(int) $date->format('n')];
    }
}
