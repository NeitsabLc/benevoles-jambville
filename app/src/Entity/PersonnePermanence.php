<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PersonnePermanenceRepository;
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
        $this->id = self::genererUuid();
        $this->nom = trim($nom);
    }

    public function getId(): string { return $this->id; }
    public function getNom(): string { return $this->nom; }
    public function isActif(): bool { return $this->actif; }

    private static function genererUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x70);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20);
    }
}
