<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Inscription;
use App\Entity\Utilisateur;
use App\Repository\InscriptionRepository;
use App\Repository\ThematiqueRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PresenceController extends AbstractController
{
    #[Route('/presences/ajouter', name: 'app_presence_ajouter', methods: ['GET', 'POST'])]
    public function ajouter(
        Request $request,
        ThematiqueRepository $thematiques,
        UtilisateurRepository $utilisateurs,
        InscriptionRepository $inscriptions,
        EntityManagerInterface $entityManager,
    ): Response {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            throw $this->createAccessDeniedException();
        }
        if ($utilisateur->getRoleMetier() === 'SALARIE_ACCUEIL') {
            throw $this->createAccessDeniedException('Le rôle accueil dispose d’un accès en consultation uniquement.');
        }

        $mode = $request->request->getString('mode') ?: $request->query->getString('mode', 'benevole');
        if (!in_array($mode, ['benevole', 'compa'], true)) {
            $mode = 'benevole';
        }
        if ($mode === 'compa' && !$utilisateur->isEquipePilote()) {
            throw $this->createAccessDeniedException('Seule l’équipe pilote peut inscrire une équipe compa.');
        }

        $erreurs = [];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('ajouter-presence', $request->request->getString('_csrf_token'))) {
                $erreurs[] = 'Le formulaire a expiré. Veuillez réessayer.';
            }

            $dateDebut = $this->lireDate($request->request->getString('date_debut'));
            $dateFin = $this->lireDate($request->request->getString('date_fin'));
            if ($dateDebut === null || $dateFin === null) {
                $erreurs[] = 'Les dates de présence sont obligatoires.';
            } elseif ($dateFin < $dateDebut) {
                $erreurs[] = 'La date de fin doit être postérieure ou égale à la date de début.';
            } elseif ($dateFin > $dateDebut->modify('+366 days')) {
                $erreurs[] = 'La durée d’une présence est limitée à un an.';
            }

            $typeCouchage = $request->request->getString('type_couchage');
            if (!in_array($typeCouchage, ['DUR', 'TENTE'], true)) {
                $erreurs[] = 'Choisissez un type de couchage.';
            }

            $commentaire = trim($request->request->getString('commentaire')) ?: null;
            $inscription = null;
            if ($mode === 'benevole') {
                $benevole = $utilisateur;
                if ($utilisateur->isEquipePilote()) {
                    $benevole = $utilisateurs->find($request->request->getString('utilisateur'));
                    if (!$benevole instanceof Utilisateur || !$benevole->isActif()) {
                        $erreurs[] = 'Choisissez un bénévole actif.';
                    }
                }
                $thematique = $thematiques->find($request->request->getString('thematique'));
                $nombreEnfants = $request->request->getInt('nombre_enfants');
                if ($thematique === null || !$thematique->isActif()) {
                    $erreurs[] = 'Choisissez une thématique active.';
                } elseif ($dateDebut !== null && $dateFin !== null && !in_array($thematique, $thematiques->findDisponiblesPour($dateDebut, $dateFin), true)) {
                    $exclusives = $thematiques->findExclusivesChevauchant($dateDebut, $dateFin);
                    $erreurs[] = $exclusives !== []
                        ? sprintf('Inscription impossible : les dates chevauchent la période exclusive de l’événement « %s ». Choisissez uniquement des dates comprises dans sa période (du %s au %s).', $exclusives[0]->getNom(), $exclusives[0]->getDateDebutEvenement()?->format('d/m/Y'), $exclusives[0]->getDateFinEvenement()?->format('d/m/Y'))
                        : 'Cette thématique n’est pas disponible pour la période choisie.';
                }
                if ($nombreEnfants < 0) {
                    $erreurs[] = 'Le nombre d’enfants ne peut pas être négatif.';
                }
                if ($dateDebut !== null && $dateFin !== null && $benevole instanceof Utilisateur && $inscriptions->chevauchePour($benevole, $dateDebut, $dateFin)) {
                    $erreurs[] = 'Cette personne a déjà une présence sur tout ou partie de cette période.';
                }
                if ($erreurs === [] && $thematique !== null && $benevole instanceof Utilisateur) {
                    $inscription = Inscription::individuelle($benevole, $thematique, $dateDebut, $dateFin, $typeCouchage, $nombreEnfants, $commentaire);
                }
            } else {
                $nomEquipe = trim($request->request->getString('nom_equipe_compa'));
                $nombrePersonnes = $request->request->getInt('nombre_personnes');
                $nombreVegetariens = $request->request->getInt('nombre_vegetariens');
                $nombreAllergieOeuf = $request->request->getInt('nombre_allergie_oeuf');
                $nombreAllergieArachide = $request->request->getInt('nombre_allergie_arachide');
                if ($nomEquipe === '' || mb_strlen($nomEquipe) > 150) {
                    $erreurs[] = 'Le nom de l’équipe compa est obligatoire et limité à 150 caractères.';
                }
                if ($nombrePersonnes < 1) {
                    $erreurs[] = 'Le nombre de personnes doit être supérieur à zéro.';
                }
                foreach (['végétariens' => $nombreVegetariens, 'allergiques aux œufs' => $nombreAllergieOeuf, 'allergiques aux arachides' => $nombreAllergieArachide] as $libelle => $effectif) {
                    if ($effectif < 0 || $effectif > $nombrePersonnes) {
                        $erreurs[] = sprintf('Le nombre de personnes %s doit être compris entre 0 et l’effectif du groupe.', $libelle);
                    }
                }
                if ($erreurs === []) {
                    $inscription = Inscription::compagnon($utilisateur, $nomEquipe, $nombrePersonnes, $dateDebut, $dateFin, $typeCouchage, $nombreVegetariens, $nombreAllergieOeuf, $nombreAllergieArachide, $commentaire);
                }
            }

            if ($inscription !== null) {
                if ($request->request->has('repas_configures')) {
                    $inscription->definirRepasSelectionnes($this->lireRepasSelectionnes($request));
                }
                $entityManager->persist($inscription);
                $entityManager->flush();
                $this->addFlash('succes', 'La présence a bien été ajoutée.');

                return $this->redirectToRoute('app_accueil', ['mois' => $dateDebut->format('Y-m')]);
            }
        }

        return $this->render('presence/ajouter.html.twig', [
            'thematiques' => $thematiques->findActives(),
            'mode' => $mode,
            'erreurs' => $erreurs,
            'peut_ajouter_compa' => $utilisateur->isEquipePilote(),
            'inscription' => null,
            'edition' => false,
            'csrf_intention' => 'ajouter-presence',
            'utilisateurs_selectionnables' => $utilisateur->isEquipePilote() ? $utilisateurs->findActifsPourInscription() : [],
            'repas_selectionnes' => $request->request->has('repas_configures') ? $this->lireRepasSelectionnes($request) : [],
            'repas_configures' => $request->request->has('repas_configures'),
        ]);
    }

    #[Route('/presences/{id}/modifier', name: 'app_presence_modifier', methods: ['GET', 'POST'])]
    public function modifier(
        string $id,
        Request $request,
        InscriptionRepository $inscriptions,
        ThematiqueRepository $thematiques,
        UtilisateurRepository $utilisateurs,
        EntityManagerInterface $entityManager,
    ): Response {
        $utilisateur = $this->utilisateurCourant();
        $inscription = $inscriptions->find($id);
        if (!$inscription instanceof Inscription || !$inscription->isActif()) {
            throw $this->createNotFoundException();
        }
        $this->verifierDroitGestion($inscription, $utilisateur);

        $mode = $inscription->getType() === 'COMPAGNON' ? 'compa' : 'benevole';
        $erreurs = [];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('modifier-presence-'.$inscription->getId(), $request->request->getString('_csrf_token'))) {
                $erreurs[] = 'Le formulaire a expiré. Veuillez réessayer.';
            }

            $dateDebut = $this->lireDate($request->request->getString('date_debut'));
            $dateFin = $this->lireDate($request->request->getString('date_fin'));
            if ($dateDebut === null || $dateFin === null) {
                $erreurs[] = 'Les dates de présence sont obligatoires.';
            } elseif ($dateFin < $dateDebut) {
                $erreurs[] = 'La date de fin doit être postérieure ou égale à la date de début.';
            } elseif ($dateFin > $dateDebut->modify('+366 days')) {
                $erreurs[] = 'La durée d’une présence est limitée à un an.';
            }

            $typeCouchage = $request->request->getString('type_couchage');
            if (!in_array($typeCouchage, ['DUR', 'TENTE'], true)) {
                $erreurs[] = 'Choisissez un type de couchage.';
            }
            $commentaire = trim($request->request->getString('commentaire')) ?: null;

            if ($mode === 'benevole') {
                $benevole = $inscription->getUtilisateur();
                if ($utilisateur->isEquipePilote()) {
                    $benevole = $utilisateurs->find($request->request->getString('utilisateur'));
                    if (!$benevole instanceof Utilisateur || !$benevole->isActif()) {
                        $erreurs[] = 'Choisissez un bénévole actif.';
                    }
                }
                $thematique = $thematiques->find($request->request->getString('thematique'));
                $nombreEnfants = $request->request->getInt('nombre_enfants');
                if ($thematique === null || !$thematique->isActif()) {
                    $erreurs[] = 'Choisissez une thématique active.';
                } elseif ($dateDebut !== null && $dateFin !== null && !in_array($thematique, $thematiques->findDisponiblesPour($dateDebut, $dateFin), true)) {
                    $exclusives = $thematiques->findExclusivesChevauchant($dateDebut, $dateFin);
                    $erreurs[] = $exclusives !== []
                        ? sprintf('Inscription impossible : les dates chevauchent la période exclusive de l’événement « %s ». Choisissez uniquement des dates comprises dans sa période (du %s au %s).', $exclusives[0]->getNom(), $exclusives[0]->getDateDebutEvenement()?->format('d/m/Y'), $exclusives[0]->getDateFinEvenement()?->format('d/m/Y'))
                        : 'Cette thématique n’est pas disponible pour la période choisie.';
                }
                if ($nombreEnfants < 0) {
                    $erreurs[] = 'Le nombre d’enfants ne peut pas être négatif.';
                }
                if ($dateDebut !== null && $dateFin !== null && $benevole instanceof Utilisateur && $inscriptions->chevauchePour($benevole, $dateDebut, $dateFin, $inscription)) {
                    $erreurs[] = 'Cette personne a déjà une présence sur tout ou partie de cette période.';
                }
                if ($erreurs === [] && $thematique !== null && $benevole instanceof Utilisateur) {
                    $inscription->modifierIndividuelle($benevole, $thematique, $dateDebut, $dateFin, $typeCouchage, $nombreEnfants, $commentaire, $utilisateur);
                }
            } else {
                $nomEquipe = trim($request->request->getString('nom_equipe_compa'));
                $nombrePersonnes = $request->request->getInt('nombre_personnes');
                $nombreVegetariens = $request->request->getInt('nombre_vegetariens');
                $nombreAllergieOeuf = $request->request->getInt('nombre_allergie_oeuf');
                $nombreAllergieArachide = $request->request->getInt('nombre_allergie_arachide');
                if ($nomEquipe === '' || mb_strlen($nomEquipe) > 150) {
                    $erreurs[] = 'Le nom de l’équipe compa est obligatoire et limité à 150 caractères.';
                }
                if ($nombrePersonnes < 1) {
                    $erreurs[] = 'Le nombre de personnes doit être supérieur à zéro.';
                }
                foreach (['végétariens' => $nombreVegetariens, 'allergiques aux œufs' => $nombreAllergieOeuf, 'allergiques aux arachides' => $nombreAllergieArachide] as $libelle => $effectif) {
                    if ($effectif < 0 || $effectif > $nombrePersonnes) {
                        $erreurs[] = sprintf('Le nombre de personnes %s doit être compris entre 0 et l’effectif du groupe.', $libelle);
                    }
                }
                if ($erreurs === []) {
                    $inscription->modifierCompagnon($nomEquipe, $nombrePersonnes, $dateDebut, $dateFin, $typeCouchage, $nombreVegetariens, $nombreAllergieOeuf, $nombreAllergieArachide, $commentaire, $utilisateur);
                }
            }

            if ($erreurs === []) {
                if ($request->request->has('repas_configures')) {
                    $inscription->definirRepasSelectionnes($this->lireRepasSelectionnes($request));
                }
                $entityManager->flush();
                $this->addFlash('succes', 'La présence a bien été modifiée.');

                return $this->redirectToRoute('app_accueil', ['mois' => $dateDebut->format('Y-m')]);
            }
        }

        return $this->render('presence/ajouter.html.twig', [
            'thematiques' => $thematiques->findActives(),
            'mode' => $mode,
            'erreurs' => $erreurs,
            'peut_ajouter_compa' => false,
            'inscription' => $inscription,
            'edition' => true,
            'csrf_intention' => 'modifier-presence-'.$inscription->getId(),
            'utilisateurs_selectionnables' => $utilisateur->isEquipePilote() ? $utilisateurs->findActifsPourInscription() : [],
            'repas_selectionnes' => $request->request->has('repas_configures') ? $this->lireRepasSelectionnes($request) : $inscription->getRepasSelectionnes(),
            'repas_configures' => true,
        ]);
    }

    #[Route('/presences/{id}/supprimer', name: 'app_presence_supprimer', methods: ['POST'])]
    public function supprimer(string $id, Request $request, InscriptionRepository $inscriptions, EntityManagerInterface $entityManager): Response
    {
        $utilisateur = $this->utilisateurCourant();
        $inscription = $inscriptions->find($id);
        if (!$inscription instanceof Inscription || !$inscription->isActif()) {
            throw $this->createNotFoundException();
        }
        $this->verifierDroitGestion($inscription, $utilisateur);
        if (!$this->isCsrfTokenValid('supprimer-presence-'.$inscription->getId(), $request->request->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('Le formulaire a expiré.');
        }

        $mois = $inscription->getDateDebut()->format('Y-m');
        $inscription->supprimer($utilisateur);
        $entityManager->flush();
        $this->addFlash('succes', 'La présence a bien été supprimée.');

        return $this->redirectToRoute('app_accueil', ['mois' => $mois]);
    }

    private function utilisateurCourant(): Utilisateur
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            throw $this->createAccessDeniedException();
        }

        return $utilisateur;
    }

    private function verifierDroitGestion(Inscription $inscription, Utilisateur $utilisateur): void
    {
        if ($utilisateur->getRoleMetier() === 'SALARIE_ACCUEIL') {
            throw $this->createAccessDeniedException('Le rôle accueil dispose d’un accès en consultation uniquement.');
        }
        $estProprietaire = $inscription->getType() === 'INDIVIDUELLE'
            && $inscription->getUtilisateur()?->getId() === $utilisateur->getId();
        if (!$utilisateur->isEquipePilote() && !$estProprietaire) {
            throw $this->createAccessDeniedException('Vous ne pouvez modifier que votre propre inscription.');
        }
    }

    private function lireDate(string $valeur): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $valeur);

        return $date !== false && $date->format('Y-m-d') === $valeur ? $date : null;
    }

    /** @return list<string> */
    private function lireRepasSelectionnes(Request $request): array
    {
        $selectionnes = [];
        foreach ($request->request->all('repas') as $date => $types) {
            if (!is_string($date) || !is_array($types) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
                continue;
            }
            foreach ($types as $type) {
                if (is_string($type) && in_array($type, ['PETIT_DEJEUNER', 'DEJEUNER', 'DINER'], true)) {
                    $selectionnes[] = $date.'|'.$type;
                }
            }
        }

        return array_values(array_unique($selectionnes));
    }
}
