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
    #[ORM\Column(name: 'date_journee', type: 'date_immutable')]
    private \DateTimeImmutable $dateJournee;

    #[ORM\ManyToOne(targetEntity: PersonnePermanence::class)]
    #[ORM\JoinColumn(name: 'personne_permanence_id', nullable: true)]
    private ?PersonnePermanence $personnePermanence = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'modifie_par_id', nullable: false)]
    private Utilisateur $modifiePar;

    public function getDateJournee(): \DateTimeImmutable { return $this->dateJournee; }
    public function getPersonnePermanence(): ?PersonnePermanence { return $this->personnePermanence; }
    public function getCommentaire(): ?string { return $this->commentaire; }
}
