<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ThematiqueRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ThematiqueRepository::class)]
#[ORM\Table(name: 'thematique', schema: 'benevole_jambville')]
final class Thematique
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\Column(length: 120)]
    private string $nom;

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\Column(name: 'ordre_affichage')]
    private int $ordreAffichage = 0;

    #[ORM\Column(name: 'date_debut_evenement', type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateDebutEvenement = null;

    #[ORM\Column(name: 'date_fin_evenement', type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateFinEvenement = null;

    #[ORM\Column(name: 'exclusive_sur_periode')]
    private bool $exclusiveSurPeriode = false;

    public function __construct(string $nom)
    {
        $this->id = self::genererUuid();
        $this->nom = trim($nom);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function getOrdreAffichage(): int { return $this->ordreAffichage; }
    public function getDateDebutEvenement(): ?\DateTimeImmutable { return $this->dateDebutEvenement; }
    public function getDateFinEvenement(): ?\DateTimeImmutable { return $this->dateFinEvenement; }
    public function isEvenement(): bool { return $this->dateDebutEvenement !== null; }
    public function isExclusiveSurPeriode(): bool { return $this->exclusiveSurPeriode; }

    public function modifier(string $nom, int $ordreAffichage, ?\DateTimeImmutable $dateDebut, ?\DateTimeImmutable $dateFin, bool $exclusive): void
    {
        $this->nom = trim($nom);
        $this->ordreAffichage = max(0, $ordreAffichage);
        $this->dateDebutEvenement = $dateDebut;
        $this->dateFinEvenement = $dateFin;
        $this->exclusiveSurPeriode = $dateDebut !== null && $dateFin !== null && $exclusive;
    }

    public function basculerActivation(): void { $this->actif = !$this->actif; }

    public function estCompatibleAvec(\DateTimeImmutable $debut, \DateTimeImmutable $fin): bool
    {
        return !$this->isEvenement() || ($debut >= $this->dateDebutEvenement && $fin <= $this->dateFinEvenement);
    }

    public function chevauche(\DateTimeImmutable $debut, \DateTimeImmutable $fin): bool
    {
        return $this->isEvenement() && $debut <= $this->dateFinEvenement && $fin >= $this->dateDebutEvenement;
    }

    private static function genererUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x70);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20);
    }
}
