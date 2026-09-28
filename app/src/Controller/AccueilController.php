<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Inscription;
use App\Entity\Utilisateur;
use App\Repository\InscriptionRepository;
use App\Repository\JourneeRepository;
use App\Repository\ThematiqueRepository;
use App\Service\RoomingService;
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
        RoomingService $rooming,
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

        $utilisateur = $this->getUser();
        $jours = [];
        $nombrePresences = 0;
        for ($date = $debutGrille; $date <= $finGrille; $date = $date->modify('+1 day')) {
            $cle = $date->format('Y-m-d');
            $presences = $presencesParJour[$cle] ?? [];
            if ($utilisateur instanceof Utilisateur) {
                usort($presences, static function (Inscription $a, Inscription $b) use ($utilisateur): int {
                    $aEstUtilisateur = 'INDIVIDUELLE' === $a->getType() && $a->getUtilisateur()?->getId() === $utilisateur->getId();
                    $bEstUtilisateur = 'INDIVIDUELLE' === $b->getType() && $b->getUtilisateur()?->getId() === $utilisateur->getId();

                    return $bEstUtilisateur <=> $aEstUtilisateur;
                });
            }
            if ($date >= $debutMois && $date <= $finMois) {
                $nombrePresences += count($presences);
            }
            $jours[] = [
                'date' => $date,
                'dans_mois' => $date->format('m') === $debutMois->format('m'),
                'presences' => $presences,
                'nombre_inscriptions' => count($presences),
                'effectif_total' => array_sum(array_map($this->effectif(...), $presences)),
                'journee' => $journeesParDate[$cle] ?? null,
            ];
        }

        $nomsMois = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        $prochainSejour = null;
        if ($utilisateur instanceof Utilisateur) {
            $dateReference = new \DateTimeImmutable('today');
            $inscription = $inscriptions->findProchainePourUtilisateur($utilisateur, $dateReference);
            if (null !== $inscription) {
                $prochainSejour = $this->construireProchainSejour($inscription, $rooming, $dateReference);
            }
        }

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
            'prochain_sejour' => $prochainSejour,
        ]);
    }

    /** @return array<string, mixed> */
    private function construireProchainSejour(
        Inscription $inscription,
        RoomingService $roomingService,
        \DateTimeImmutable $dateReference,
    ): array {
        $repasSelectionnes = array_fill_keys($inscription->getRepasSelectionnes(), true);
        $libellesRepas = [
            'PETIT_DEJEUNER' => 'Petit-déjeuner',
            'DEJEUNER' => 'Déjeuner',
            'DINER' => 'Dîner',
        ];
        $jours = [];
        for ($date = $inscription->getDateDebut(); $date <= $inscription->getDateFin(); $date = $date->modify('+1 day')) {
            $repas = [];
            foreach ($libellesRepas as $type => $libelle) {
                if (isset($repasSelectionnes[$date->format('Y-m-d').'|'.$type])) {
                    $repas[] = $libelle;
                }
            }
            $jours[] = [
                'date' => $date,
                'jour' => ucfirst($this->nomJour($date)),
                'date_courte' => $date->format('j').' '.$this->nomMoisCourt($date),
                'repas' => $repas,
                'arrivee' => $date == $inscription->getDateDebut(),
                'depart' => $date == $inscription->getDateFin(),
            ];
        }

        $affectationsParChambre = [];
        foreach ($roomingService->trouverAffectationsPour($inscription) as $affectation) {
            $code = $affectation['chambre'];
            $affectationsParChambre[$code] ??= [
                'nom' => $affectation['nom'],
                'batiment' => $affectation['batiment'],
                'dates' => [],
            ];
            $affectationsParChambre[$code]['dates'][] = $affectation['date'];
        }

        $nombreJours = $inscription->getDateDebut()->diff($inscription->getDateFin())->days + 1;
        $rooming = [];
        foreach ($affectationsParChambre as $affectation) {
            $dates = $affectation['dates'];
            $rooming[] = [
                'nom' => $affectation['nom'],
                'batiment' => $affectation['batiment'],
                'periode' => count($dates) === $nombreJours
                    ? 'Attribuée pour tout le séjour'
                    : $this->libellePeriodeRooming($dates),
            ];
        }

        $ecart = (int) $dateReference->diff($inscription->getDateDebut())->format('%r%a');
        $statut = match (true) {
            $inscription->getDateDebut() <= $dateReference && $inscription->getDateFin() >= $dateReference => 'En cours',
            1 === $ecart => 'Demain',
            default => sprintf('Dans %d jours', max(0, $ecart)),
        };

        return [
            'id' => $inscription->getId(),
            'periode' => $this->libellePeriode($inscription->getDateDebut(), $inscription->getDateFin()),
            'statut' => $statut,
            'thematique' => $inscription->getThematique()?->getNom(),
            'jours' => $jours,
            'rooming' => $rooming,
        ];
    }

    private function libellePeriode(\DateTimeImmutable $debut, \DateTimeImmutable $fin): string
    {
        if ($debut == $fin) {
            return sprintf('Le %s %d %s', $this->nomJour($debut), (int) $debut->format('j'), $this->nomMois($debut));
        }
        if ($debut->format('Y-m') === $fin->format('Y-m')) {
            return sprintf(
                'Du %s %d au %s %d %s',
                $this->nomJour($debut),
                (int) $debut->format('j'),
                $this->nomJour($fin),
                (int) $fin->format('j'),
                $this->nomMois($fin),
            );
        }

        return sprintf(
            'Du %s %d %s au %s %d %s',
            $this->nomJour($debut),
            (int) $debut->format('j'),
            $this->nomMois($debut),
            $this->nomJour($fin),
            (int) $fin->format('j'),
            $this->nomMois($fin),
        );
    }

    /** @param list<\DateTimeImmutable> $dates */
    private function libellePeriodeRooming(array $dates): string
    {
        if (1 === count($dates)) {
            return 'Le '.$dates[0]->format('j').' '.$this->nomMois($dates[0]);
        }

        return sprintf(
            'Du %d %s au %d %s',
            (int) $dates[0]->format('j'),
            $this->nomMois($dates[0]),
            (int) $dates[array_key_last($dates)]->format('j'),
            $this->nomMois($dates[array_key_last($dates)]),
        );
    }

    private function nomJour(\DateTimeImmutable $date): string
    {
        return [1 => 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'][(int) $date->format('N')];
    }

    private function nomMois(\DateTimeImmutable $date): string
    {
        return [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'][(int) $date->format('n')];
    }

    private function nomMoisCourt(\DateTimeImmutable $date): string
    {
        return [1 => 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'][(int) $date->format('n')];
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

    private function effectif(Inscription $inscription): int
    {
        return 'COMPAGNON' === $inscription->getType()
            ? (int) $inscription->getNombrePersonnes()
            : 1 + $inscription->getNombreEnfants();
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
