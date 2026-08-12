<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Thematique;
use App\Repository\ThematiqueRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/administration/thematiques')]
final class ThematiqueController extends AbstractController
{
    #[Route('', name: 'app_admin_thematiques', methods: ['GET'])]
    public function index(ThematiqueRepository $repository): Response
    {
        $this->garantirAcces();

        return $this->render('thematique/index.html.twig', ['thematiques' => $repository->findToutes()]);
    }

    #[Route('/ajouter', name: 'app_admin_thematique_ajouter', methods: ['GET', 'POST'])]
    public function ajouter(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->garantirAcces();
        $erreurs = [];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('creer-thematique', $request->request->getString('_csrf_token'))) {
                $erreurs[] = 'Le formulaire a expiré. Veuillez réessayer.';
            }
            $thematique = new Thematique($request->request->getString('nom'));
            $erreurs = [...$erreurs, ...$this->renseigner($thematique, $request)];
            if ([] === $erreurs) {
                try {
                    $entityManager->persist($thematique);
                    $entityManager->flush();
                    $this->addFlash('succes', 'La thématique a bien été ajoutée.');

                    return $this->redirectToRoute('app_admin_thematiques');
                } catch (UniqueConstraintViolationException) {
                    $this->addFlash('erreur', 'Une thématique portant ce nom existe déjà.');

                    return $this->redirectToRoute('app_admin_thematiques');
                }
            }
        }

        return $this->render('thematique/ajouter.html.twig', ['erreurs' => $erreurs]);
    }

    #[Route('/{id}/modifier', name: 'app_admin_thematique_modifier', methods: ['GET', 'POST'])]
    public function modifier(string $id, Request $request, ThematiqueRepository $repository, EntityManagerInterface $entityManager): Response
    {
        $this->garantirAcces();
        $thematique = $repository->find($id);
        if (!$thematique instanceof Thematique) {
            throw $this->createNotFoundException();
        }
        $erreurs = [];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('modifier-thematique-'.$id, $request->request->getString('_csrf_token'))) {
                $erreurs[] = 'Le formulaire a expiré. Veuillez réessayer.';
            }
            $erreurs = [...$erreurs, ...$this->renseigner($thematique, $request)];
            if ([] === $erreurs) {
                try {
                    $entityManager->flush();
                    $this->addFlash('succes', 'La thématique a bien été modifiée.');

                    return $this->redirectToRoute('app_admin_thematiques');
                } catch (UniqueConstraintViolationException) {
                    $this->addFlash('erreur', 'Une thématique portant ce nom existe déjà.');

                    return $this->redirectToRoute('app_admin_thematique_modifier', ['id' => $id]);
                }
            }
        }

        return $this->render('thematique/modifier.html.twig', ['thematique' => $thematique, 'erreurs' => $erreurs]);
    }

    #[Route('/{id}/activation', name: 'app_admin_thematique_activation', methods: ['POST'])]
    public function activation(string $id, Request $request, ThematiqueRepository $repository, EntityManagerInterface $entityManager): Response
    {
        $this->garantirAcces();
        $thematique = $repository->find($id);
        if (!$thematique instanceof Thematique) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('activation-thematique-'.$id, $request->request->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('Le formulaire a expiré.');
        }
        $thematique->basculerActivation();
        $entityManager->flush();
        $this->addFlash('succes', $thematique->isActif() ? 'La thématique a été réactivée.' : 'La thématique a été désactivée.');

        return $this->redirectToRoute('app_admin_thematiques');
    }

    /** @return list<string> */
    private function renseigner(Thematique $thematique, Request $request): array
    {
        $nom = trim($request->request->getString('nom'));
        $debutBrut = $request->request->getString('date_debut_evenement');
        $finBrut = $request->request->getString('date_fin_evenement');
        $debut = $this->lireDate($debutBrut);
        $fin = $this->lireDate($finBrut);
        $erreurs = [];
        if ('' === $nom || mb_strlen($nom) > 120) {
            $erreurs[] = 'Le nom est obligatoire et limité à 120 caractères.';
        }
        if (('' === $debutBrut) !== ('' === $finBrut)) {
            $erreurs[] = 'Renseignez les deux dates de la période événementielle.';
        }
        if (('' !== $debutBrut && null === $debut) || ('' !== $finBrut && null === $fin)) {
            $erreurs[] = 'La période événementielle contient une date invalide.';
        }
        if (null !== $debut && null !== $fin && $fin < $debut) {
            $erreurs[] = 'La fin de la période doit être postérieure ou égale au début.';
        }
        if ([] === $erreurs) {
            $thematique->modifier($nom, max(0, $request->request->getInt('ordre_affichage')), $debut, $fin, $request->request->getBoolean('exclusive_sur_periode'));
        }

        return $erreurs;
    }

    private function lireDate(string $valeur): ?\DateTimeImmutable
    {
        if ('' === $valeur) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $valeur);

        return false !== $date && $date->format('Y-m-d') === $valeur ? $date : null;
    }

    private function garantirAcces(): void
    {
        if (!$this->isGranted('ROLE_EQUIPE_PILOTE')) {
            throw $this->createAccessDeniedException();
        }
    }
}
