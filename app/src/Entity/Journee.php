<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\JourneeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JourneeRepository::class)]
#[ORM\Table(name: 'journee', schema: 'benevole_jambville')]
final class Journee
{
    #[ORM\Id]
    // Doctrine exige un identifiant scalaire pour construire sa clé d'identité.
    // La colonne PostgreSQL reste de type DATE ; sa représentation ISO est utilisée côté ORM.
    #[ORM\Column(name: 'date_journee', length: 10)]
    private string $dateJournee;

    #[ORM\ManyToOne(targetEntity: PersonnePermanence::class)]
    #[ORM\JoinColumn(name: 'personne_permanence_id', nullable: true)]
    private ?PersonnePermanence $personnePermanence = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'modifie_par_id', nullable: false)]
    private Utilisateur $modifiePar;

    public function __construct(\DateTimeImmutable $dateJournee, Utilisateur $modifiePar)
    {
        $this->dateJournee = $dateJournee->format('Y-m-d');
        $this->modifiePar = $modifiePar;
    }

    public function getDateJournee(): \DateTimeImmutable { return new \DateTimeImmutable($this->dateJournee); }
    public function getPersonnePermanence(): ?PersonnePermanence { return $this->personnePermanence; }
    public function getCommentaire(): ?string { return $this->commentaire; }

    public function definirPermanence(?PersonnePermanence $personne, Utilisateur $modifiePar): void
    {
        $this->personnePermanence = $personne;
        $this->modifiePar = $modifiePar;
    }

    public function definirCommentaire(?string $commentaire, Utilisateur $modifiePar): void
    {
        $commentaire = trim((string) $commentaire);
        $this->commentaire = $commentaire === '' ? null : $commentaire;
        $this->modifiePar = $modifiePar;
    }

    public function estVide(): bool
    {
        return $this->personnePermanence === null && $this->commentaire === null;
    }
}
