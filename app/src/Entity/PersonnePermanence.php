<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PersonnePermanenceRepository;
use App\Util\UuidV7;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PersonnePermanenceRepository::class)]
#[ORM\Table(name: 'personne_permanence', schema: 'benevole_jambville')]
final class PersonnePermanence
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\Column(length: 150)]
    private string $nom;

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\Column(name: 'ordre_affichage')]
    private int $ordreAffichage = 0;

    public function __construct(string $nom)
    {
        $this->id = UuidV7::generate();
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
}
