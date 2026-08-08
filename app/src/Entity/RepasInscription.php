<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'repas_inscription', schema: 'benevole_jambville')]
final class RepasInscription
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Inscription::class, inversedBy: 'repas')]
    #[ORM\JoinColumn(name: 'inscription_id', nullable: false, onDelete: 'CASCADE')]
    private Inscription $inscription;

    #[ORM\Column(name: 'date_repas', type: 'date_immutable')]
    private \DateTimeImmutable $dateRepas;

    #[ORM\Column(name: 'type_repas', length: 20)]
    private string $typeRepas;

    #[ORM\Column]
    private bool $selectionne = true;

    public function __construct(Inscription $inscription, \DateTimeImmutable $dateRepas, string $typeRepas)
    {
        $this->id = self::genererUuid();
        $this->inscription = $inscription;
        $this->dateRepas = $dateRepas;
        $this->typeRepas = $typeRepas;
    }

    public function getCle(): string
    {
        return $this->dateRepas->format('Y-m-d').'|'.$this->typeRepas;
    }

    public function selectionner(bool $selectionne): void
    {
        $this->selectionne = $selectionne;
    }

    public function isSelectionne(): bool
    {
        return $this->selectionne;
    }

    public function getDateRepas(): \DateTimeImmutable
    {
        return $this->dateRepas;
    }

    public function getTypeRepas(): string
    {
        return $this->typeRepas;
    }

    private static function genererUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20);
    }
}
