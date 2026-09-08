<?php

declare(strict_types=1);

namespace App\Entity;

use App\Util\UuidV7;
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
        $this->id = UuidV7::generate();
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
}
