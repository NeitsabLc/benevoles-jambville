<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
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

    public function getNom(): string { return $this->nom; }
}
