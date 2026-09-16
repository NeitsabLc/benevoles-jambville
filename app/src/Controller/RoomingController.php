<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Inscription;
use App\Entity\Utilisateur;
use App\Repository\InscriptionRepository;
use App\Service\RoomingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RoomingController extends AbstractController
{
    #[Route('/rooming', name: 'app_rooming', methods: ['GET', 'POST'])]
    public function index(Request $request, InscriptionRepository $inscriptions, RoomingService $rooming): Response
    {
        $this->garantirAccesEquipe();
        $aujourdhui = new \DateTimeImmutable('today');
        $debutParDefaut = $aujourdhui->modify('monday this week');
        $finParDefaut = $aujourdhui->modify('sunday this week');
        $debut = $this->lireDate($request->isMethod('POST') ? $request->request->getString('periode_debut') : $request->query->getString('debut'))
            ?? $debutParDefaut;
        $fin = $this->lireDate($request->isMethod('POST') ? $request->request->getString('periode_fin') : $request->query->getString('fin'))
            ?? $finParDefaut;
        if ($fin < $debut) {
            [$debut, $fin] = [$fin, $debut];
        }

        if ($request->isMethod('POST')) {
            $inscription = $inscriptions->find($request->request->getString('inscription'));
            if (!$inscription instanceof Inscription) {
                throw $this->createNotFoundException('Cette présence est introuvable.');
            }
            $dateNuit = $this->lireDate($request->request->getString('date_nuit'));
            if (null === $dateNuit) {
                throw $this->createNotFoundException('Cette date est invalide.');
            }
            $jeton = $request->request->getString('_csrf_token');
            $jetonValide = $this->isCsrfTokenValid('rooming-date-'.$dateNuit->format('Y-m-d'), $jeton)
                || $this->isCsrfTokenValid('rooming-'.$inscription->getId().'-'.$dateNuit->format('Y-m-d'), $jeton);
            if (!$jetonValide) {
                throw $this->createAccessDeniedException('Le formulaire a expiré.');
            }
            $utilisateur = $this->getUser();
            if (!$utilisateur instanceof Utilisateur) {
                throw $this->createAccessDeniedException();
            }

            $estRetrait = 'retirer' === $request->request->getString('action');
            $codeChambre = $estRetrait ? null : $request->request->getString('chambre');
            $codeChambre = '' !== $codeChambre ? $codeChambre : null;
            $portee = $request->request->getString('portee', 'periode');

            try {
                if ($dateNuit < $inscription->getDateDebut() || $dateNuit > $inscription->getDateFin()) {
                    throw new \DomainException('Cette personne n’est pas présente à la date sélectionnée.');
                }
                if ('nuit' === $portee) {
                    $rooming->affecterNuit($dateNuit, $inscription, $codeChambre, $utilisateur);
                    $this->addFlash('succes', $estRetrait
                        ? 'La personne a été retirée de la chambre pour cette nuit.'
                        : 'La personne a été ajoutée à la chambre pour cette nuit.');
                } else {
                    $rooming->affecterPeriode($inscription, $codeChambre, $utilisateur);
                    $this->addFlash('succes', $estRetrait
                        ? 'La personne a été retirée de la chambre pour toute sa période de présence.'
                        : 'La chambre a bien été affectée pour toute la période de présence.');
                }
            } catch (\DomainException $exception) {
                $this->addFlash('erreur', $exception->getMessage());
            }

            return $this->redirectToRoute('app_rooming', [
                'debut' => $debut->format('Y-m-d'),
                'fin' => $fin->format('Y-m-d'),
            ]);
        }

        $inscriptionsPeriode = $inscriptions->findPourRooming($debut, $fin);
        $affectations = $rooming->trouverAffectations($debut, $fin);
        $jours = [];
        $presencesParJour = [];
        $sansChambreParJour = [];
        for ($jour = $debut; $jour <= $fin; $jour = $jour->modify('+1 day')) {
            $cleJour = $jour->format('Y-m-d');
            $jours[] = [
                'date' => $jour,
                'jour' => $jour->format('d'),
                'jour_semaine' => $this->nomJour((int) $jour->format('N')),
                'mois' => $this->nomMois((int) $jour->format('n')),
            ];
            $presencesParJour[$cleJour] = [];
            $sansChambreParJour[$cleJour] = [];
        }

        $chambres = [];
        foreach ($rooming->getChambres() as $code => $chambre) {
            $occupation = [];
            foreach ($jours as $jour) {
                $occupation[$jour['date']->format('Y-m-d')] = [
                    'occupees' => 0,
                    'disponibles' => $chambre['capacite'],
                    'occupants' => [],
                ];
            }
            $chambres[$code] = [...$chambre, 'occupation' => $occupation];
        }

        foreach ($inscriptionsPeriode as $inscription) {
            $effectif = $this->effectif($inscription);
            $libelle = $this->libellePresence($inscription);
            $premierJour = max($debut, $inscription->getDateDebut());
            $dernierJour = min($fin, $inscription->getDateFin());
            for ($jour = $premierJour; $jour <= $dernierJour; $jour = $jour->modify('+1 day')) {
                $cleJour = $jour->format('Y-m-d');
                $affectation = $affectations[$cleJour][$inscription->getId()] ?? null;
                $presence = [
                    'inscription' => $inscription,
                    'libelle' => $libelle,
                    'effectif' => $effectif,
                    'chambre' => $affectation['chambre'] ?? null,
                ];
                $presencesParJour[$cleJour][] = $presence;

                if (null !== $affectation && isset($chambres[$affectation['chambre']])) {
                    $chambres[$affectation['chambre']]['occupation'][$cleJour]['occupees'] += $affectation['nombre_places'];
                    $chambres[$affectation['chambre']]['occupation'][$cleJour]['disponibles'] -= $affectation['nombre_places'];
                    $chambres[$affectation['chambre']]['occupation'][$cleJour]['occupants'][] = [
                        'inscription' => $inscription,
                        'libelle' => $libelle,
                        'nombre' => $affectation['nombre_places'],
                    ];
                } else {
                    $sansChambreParJour[$cleJour][] = $presence;
                }
            }
        }

        return $this->render('rooming/index.html.twig', [
            'debut' => $debut,
            'fin' => $fin,
            'jours' => $jours,
            'chambres' => $chambres,
            'presences_par_jour' => $presencesParJour,
            'sans_chambre_par_jour' => $sansChambreParJour,
        ]);
    }

    private function nomMois(int $mois): string
    {
        return [
            1 => 'janvier',
            2 => 'février',
            3 => 'mars',
            4 => 'avril',
            5 => 'mai',
            6 => 'juin',
            7 => 'juillet',
            8 => 'août',
            9 => 'septembre',
            10 => 'octobre',
            11 => 'novembre',
            12 => 'décembre',
        ][$mois];
    }

    private function nomJour(int $jour): string
    {
        return [
            1 => 'lundi',
            2 => 'mardi',
            3 => 'mercredi',
            4 => 'jeudi',
            5 => 'vendredi',
            6 => 'samedi',
            7 => 'dimanche',
        ][$jour];
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

    private function effectif(Inscription $inscription): int
    {
        return 'COMPAGNON' === $inscription->getType()
            ? (int) $inscription->getNombrePersonnes()
            : 1 + $inscription->getNombreEnfants();
    }

    private function libellePresence(Inscription $inscription): string
    {
        if ('COMPAGNON' === $inscription->getType()) {
            return (string) $inscription->getNomEquipeCompa();
        }
        $utilisateur = $inscription->getUtilisateur();
        $libelle = null !== $utilisateur ? $utilisateur->getPrenom().' '.$utilisateur->getNom() : 'Bénévole';
        if ($inscription->getNombreEnfants() > 0) {
            $libelle .= ' + '.$inscription->getNombreEnfants().' enfant'.($inscription->getNombreEnfants() > 1 ? 's' : '');
        }

        return $libelle;
    }
}
