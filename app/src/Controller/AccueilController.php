<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\InscriptionRepository;
use App\Repository\JourneeRepository;
use App\Repository\ThematiqueRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AccueilController extends AbstractController
{
    #[Route('/', name: 'app_accueil', methods: ['GET'])]
    public function __invoke(
        Request $request,
        InscriptionRepository $inscriptions,
        JourneeRepository $journees,
        ThematiqueRepository $thematiques,
    ): Response {
        $mois = $this->lireMois($request->query->getString('mois'));
        $debutMois = $mois->modify('first day of this month');
        $finMois = $mois->modify('last day of this month');
        $debutGrille = $debutMois->modify('monday this week');
        $finGrille = $finMois->modify('sunday this week');

        $thematiquesActives = $thematiques->findActives();
        usort($thematiquesActives, static fn ($a, $b): int => strcasecmp($a->getNom(), $b->getNom()));
        $filtre = $request->query->getString('filtre') ?: null;
        $filtresValides = array_merge(['compa'], array_map(static fn ($thematique) => $thematique->getId(), $thematiquesActives));
        if (null !== $filtre && !in_array($filtre, $filtresValides, true)) {
            $filtre = null;
        }

        $presencesParJour = [];
        foreach ($inscriptions->findPourCalendrier($debutGrille, $finGrille, $filtre) as $inscription) {
            $debut = max($inscription->getDateDebut(), $debutGrille);
            $fin = min($inscription->getDateFin(), $finGrille);
            for ($date = $debut; $date <= $fin; $date = $date->modify('+1 day')) {
                $presencesParJour[$date->format('Y-m-d')][] = $inscription;
            }
        }

        $journeesParDate = [];
        foreach ($journees->findEntre($debutGrille, $finGrille) as $journee) {
            $journeesParDate[$journee->getDateJournee()->format('Y-m-d')] = $journee;
        }

        $jours = [];
        $nombrePresences = 0;
        for ($date = $debutGrille; $date <= $finGrille; $date = $date->modify('+1 day')) {
            $cle = $date->format('Y-m-d');
            if ($date >= $debutMois && $date <= $finMois) {
                $nombrePresences += count($presencesParJour[$cle] ?? []);
            }
            $jours[] = [
                'date' => $date,
                'dans_mois' => $date->format('m') === $debutMois->format('m'),
                'presences' => $presencesParJour[$cle] ?? [],
                'journee' => $journeesParDate[$cle] ?? null,
            ];
        }

        $nomsMois = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

        return $this->render('accueil/index.html.twig', [
            'jours' => $jours,
            'mois' => $debutMois,
            'libelle_mois' => ucfirst($nomsMois[(int) $debutMois->format('n')]).' '.$debutMois->format('Y'),
            'mois_precedent' => $debutMois->modify('-1 month')->format('Y-m'),
            'mois_suivant' => $debutMois->modify('+1 month')->format('Y-m'),
            'thematiques' => $thematiquesActives,
            'filtre' => $filtre,
            'nombre_presences' => $nombrePresences,
            'classes_thematiques' => $this->classesThematiques(),
        ]);
    }

    private function lireMois(string $valeur): \DateTimeImmutable
    {
        if (1 === preg_match('/^\d{4}-\d{2}$/', $valeur)) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m', $valeur);
            if (false !== $date) {
                return $date;
            }
        }

        return new \DateTimeImmutable('first day of this month');
    }

    /** @return array<string, string> */
    private function classesThematiques(): array
    {
        return [
            'Accueil' => 'theme-accueil',
            'Chantier' => 'theme-chantier',
            'Audiovisuel' => 'theme-audiovisuel',
            'Technique infra' => 'theme-infra',
            'Abeille' => 'theme-abeille',
            'Scout Market' => 'theme-market',
            'Au service' => 'theme-service',
        ];
    }
}
