<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Inscription;
use App\Entity\Journee;
use App\Entity\PersonnePermanence;
use App\Entity\Utilisateur;
use App\Repository\InscriptionRepository;
use App\Repository\JourneeRepository;
use App\Repository\PersonnePermanenceRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
                $besoinCouchage = $inscription->getType() === 'INDIVIDUELLE'
                    ? trim((string) $inscription->getUtilisateur()?->getBesoinCouchage())
                    : '';
                $presence = [
                    'libelle' => $libelle,
                    'effectif' => $effectif,
                    'est_equipe' => $inscription->getType() === 'COMPAGNON',
                    'besoin_couchage' => $besoinCouchage !== '' ? $besoinCouchage : null,
                ];
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

    #[Route('/administration/calendrier', name: 'app_admin_calendrier', methods: ['GET', 'POST'])]
    public function calendrier(
        Request $request,
        PersonnePermanenceRepository $personnes,
        JourneeRepository $journees,
        EntityManagerInterface $entityManager,
    ): Response
    {
        $this->garantirAccesEquipe();
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur) throw $this->createAccessDeniedException();

        if ($request->isMethod('POST')) {
            $action = $request->request->getString('action');
            if (!$this->isCsrfTokenValid('configurer-calendrier', $request->request->getString('_csrf_token'))) {
                $this->addFlash('erreur', 'Le formulaire a expiré. Veuillez réessayer.');
                return $this->redirectToRoute('app_admin_calendrier');
            }

            if ($action === 'ajouter_personne') {
                $nom = trim($request->request->getString('nom_personne'));
                if ($nom === '' || mb_strlen($nom) > 150) {
                    $this->addFlash('erreur', 'Le nom est obligatoire et limité à 150 caractères.');
                } else {
                    try {
                        $entityManager->persist(new PersonnePermanence($nom));
                        $entityManager->flush();
                        $this->addFlash('succes', 'La personne a été ajoutée à la liste.');
                    } catch (UniqueConstraintViolationException) {
                        $this->addFlash('erreur', 'Cette personne figure déjà dans la liste.');
                    }
                }
                return $this->redirectToRoute('app_admin_calendrier');
            }

            $debut = $this->lireDate($request->request->getString('date_debut'));
            $fin = $this->lireDate($request->request->getString('date_fin'));
            if ($debut === null || $fin === null || $fin < $debut || $fin > $debut->modify('+366 days')) {
                $this->addFlash('erreur', 'Choisissez une période valide, limitée à un an.');
                return $this->redirectToRoute('app_admin_calendrier');
            }

            $mode = $request->request->getString('mode') === 'remplacer' ? 'remplacer' : 'completer';
            $joursExistants = [];
            foreach ($journees->findEntre($debut, $fin) as $journee) {
                $joursExistants[$journee->getDateJournee()->format('Y-m-d')] = $journee;
            }

            if ($action === 'permanence') {
                $personne = $personnes->find($request->request->getString('personne'));
                if (!$personne instanceof PersonnePermanence || !$personne->isActif()) {
                    $this->addFlash('erreur', 'Choisissez une personne de permanence.');
                    return $this->redirectToRoute('app_admin_calendrier');
                }
                $modifies = $this->appliquerPeriode($debut, $fin, $joursExistants, $entityManager, $utilisateur, $mode, 'permanence', $personne);
                $this->addFlash('succes', $modifies.' jour'.($modifies > 1 ? 's ont' : ' a').' été mis à jour pour la permanence.');
            } elseif ($action === 'commentaire') {
                $commentaire = trim($request->request->getString('commentaire'));
                if (mb_strlen($commentaire) > 1000) {
                    $this->addFlash('erreur', 'Le commentaire est limité à 1 000 caractères.');
                    return $this->redirectToRoute('app_admin_calendrier');
                }
                $modifies = $this->appliquerPeriode($debut, $fin, $joursExistants, $entityManager, $utilisateur, $mode, 'commentaire', $commentaire);
                $this->addFlash('succes', $commentaire === '' ? 'Les commentaires de la période ont été retirés.' : $modifies.' jour'.($modifies > 1 ? 's ont' : ' a').' été mis à jour avec ce commentaire.');
            }

            $entityManager->flush();
            return $this->redirectToRoute('app_admin_calendrier');
        }

        $aujourdhui = new \DateTimeImmutable('today');

        return $this->render('espace_prive/calendrier.html.twig', [
            'personnes' => $personnes->findActives(),
            'date_debut' => $aujourdhui,
            'date_fin' => $aujourdhui->modify('+6 days'),
            'journees' => $journees->findEntre($aujourdhui, $aujourdhui->modify('+41 days')),
        ]);
    }

    /** @param array<string, Journee> $existants */
    private function appliquerPeriode(\DateTimeImmutable $debut, \DateTimeImmutable $fin, array $existants, EntityManagerInterface $entityManager, Utilisateur $utilisateur, string $mode, string $champ, mixed $valeur): int
    {
        $modifies = 0;
        for ($date = $debut; $date <= $fin; $date = $date->modify('+1 day')) {
            $journee = $existants[$date->format('Y-m-d')] ?? new Journee($date, $utilisateur);
            $dejaRenseigne = $champ === 'permanence' ? $journee->getPersonnePermanence() !== null : $journee->getCommentaire() !== null;
            if ($mode === 'completer' && $dejaRenseigne) continue;
            if ($champ === 'permanence') $journee->definirPermanence($valeur, $utilisateur);
            else $journee->definirCommentaire($valeur, $utilisateur);
            if ($journee->estVide()) {
                if (isset($existants[$date->format('Y-m-d')])) $entityManager->remove($journee);
            } else {
                $entityManager->persist($journee);
            }
            ++$modifies;
        }

        return $modifies;
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
