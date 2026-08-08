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
}
