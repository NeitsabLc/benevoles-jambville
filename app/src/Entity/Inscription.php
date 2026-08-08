<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InscriptionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InscriptionRepository::class)]
#[ORM\Table(name: 'inscription', schema: 'benevole_jambville')]
final class Inscription
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\Column(length: 20)]
    private string $type;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'utilisateur_id', nullable: true)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\ManyToOne(targetEntity: Thematique::class)]
    #[ORM\JoinColumn(name: 'thematique_id', nullable: true)]
    private ?Thematique $thematique = null;

    #[ORM\Column(name: 'nom_equipe_compa', length: 150, nullable: true)]
    private ?string $nomEquipeCompa = null;

    #[ORM\Column(name: 'nombre_personnes', nullable: true)]
    private ?int $nombrePersonnes = null;

    #[ORM\Column(name: 'date_debut', type: 'date_immutable')]
    private \DateTimeImmutable $dateDebut;

    #[ORM\Column(name: 'date_fin', type: 'date_immutable')]
    private \DateTimeImmutable $dateFin;

    #[ORM\Column(name: 'type_couchage', length: 10)]
    private string $typeCouchage;

    #[ORM\Column(name: 'nombre_enfants')]
    private int $nombreEnfants = 0;

    #[ORM\Column(name: 'nombre_vegetariens')]
    private int $nombreVegetariens = 0;

    #[ORM\Column(name: 'nombre_allergie_oeuf')]
    private int $nombreAllergieOeuf = 0;

    #[ORM\Column(name: 'nombre_allergie_arachide')]
    private int $nombreAllergieArachide = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'cree_par_id', nullable: false)]
    private Utilisateur $creePar;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'modifie_par_id', nullable: false)]
    private Utilisateur $modifiePar;

    /** @var Collection<int, RepasInscription> */
    #[ORM\OneToMany(mappedBy: 'inscription', targetEntity: RepasInscription::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $repas;

    private function __construct(Utilisateur $auteur, \DateTimeImmutable $dateDebut, \DateTimeImmutable $dateFin, string $typeCouchage, ?string $commentaire)
    {
        $this->id = self::genererUuid();
        $this->creePar = $auteur;
        $this->modifiePar = $auteur;
        $this->dateDebut = $dateDebut;
        $this->dateFin = $dateFin;
        $this->typeCouchage = $typeCouchage;
        $this->commentaire = $commentaire;
        $this->repas = new ArrayCollection();
    }

    public static function individuelle(Utilisateur $utilisateur, Thematique $thematique, \DateTimeImmutable $dateDebut, \DateTimeImmutable $dateFin, string $typeCouchage, int $nombreEnfants, ?string $commentaire): self
    {
        $inscription = new self($utilisateur, $dateDebut, $dateFin, $typeCouchage, $commentaire);
        $inscription->type = 'INDIVIDUELLE';
        $inscription->utilisateur = $utilisateur;
        $inscription->thematique = $thematique;
        $inscription->nombreEnfants = $nombreEnfants;
        $inscription->genererRepas();

        return $inscription;
    }

    public static function compagnon(Utilisateur $auteur, string $nomEquipe, int $nombrePersonnes, \DateTimeImmutable $dateDebut, \DateTimeImmutable $dateFin, string $typeCouchage, int $nombreVegetariens, int $nombreAllergieOeuf, int $nombreAllergieArachide, ?string $commentaire): self
    {
        $inscription = new self($auteur, $dateDebut, $dateFin, $typeCouchage, $commentaire);
        $inscription->type = 'COMPAGNON';
        $inscription->nomEquipeCompa = trim($nomEquipe);
        $inscription->nombrePersonnes = $nombrePersonnes;
        $inscription->nombreVegetariens = $nombreVegetariens;
        $inscription->nombreAllergieOeuf = $nombreAllergieOeuf;
        $inscription->nombreAllergieArachide = $nombreAllergieArachide;
        $inscription->genererRepas();

        return $inscription;
    }

    private function genererRepas(): void
    {
        for ($date = $this->dateDebut; $date <= $this->dateFin; $date = $date->modify('+1 day')) {
            foreach (['PETIT_DEJEUNER', 'DEJEUNER', 'DINER'] as $typeRepas) {
                $this->repas->add(new RepasInscription($this, $date, $typeRepas));
            }
        }
    }

    public function modifierIndividuelle(Utilisateur $utilisateur, Thematique $thematique, \DateTimeImmutable $dateDebut, \DateTimeImmutable $dateFin, string $typeCouchage, int $nombreEnfants, ?string $commentaire, Utilisateur $auteur): void
    {
        if ($this->type !== 'INDIVIDUELLE') {
            throw new \LogicException('Une inscription compa ne peut pas devenir individuelle.');
        }

        $this->utilisateur = $utilisateur;
        $this->thematique = $thematique;
        $this->nombreEnfants = $nombreEnfants;
        $this->modifierPeriodeEtAccueil($dateDebut, $dateFin, $typeCouchage, $commentaire, $auteur);
    }

    public function modifierCompagnon(string $nomEquipe, int $nombrePersonnes, \DateTimeImmutable $dateDebut, \DateTimeImmutable $dateFin, string $typeCouchage, int $nombreVegetariens, int $nombreAllergieOeuf, int $nombreAllergieArachide, ?string $commentaire, Utilisateur $auteur): void
    {
        if ($this->type !== 'COMPAGNON') {
            throw new \LogicException('Une inscription individuelle ne peut pas devenir compa.');
        }

        $this->nomEquipeCompa = trim($nomEquipe);
        $this->nombrePersonnes = $nombrePersonnes;
        $this->nombreVegetariens = $nombreVegetariens;
        $this->nombreAllergieOeuf = $nombreAllergieOeuf;
        $this->nombreAllergieArachide = $nombreAllergieArachide;
        $this->modifierPeriodeEtAccueil($dateDebut, $dateFin, $typeCouchage, $commentaire, $auteur);
    }

    private function modifierPeriodeEtAccueil(\DateTimeImmutable $dateDebut, \DateTimeImmutable $dateFin, string $typeCouchage, ?string $commentaire, Utilisateur $auteur): void
    {
        $this->dateDebut = $dateDebut;
        $this->dateFin = $dateFin;
        $this->typeCouchage = $typeCouchage;
        $this->commentaire = $commentaire;
        $this->modifiePar = $auteur;
        $this->synchroniserRepas();
    }

    private function synchroniserRepas(): void
    {
        $repasAttendus = [];
        for ($date = $this->dateDebut; $date <= $this->dateFin; $date = $date->modify('+1 day')) {
            foreach (['PETIT_DEJEUNER', 'DEJEUNER', 'DINER'] as $typeRepas) {
                $repasAttendus[$date->format('Y-m-d').'|'.$typeRepas] = [$date, $typeRepas];
            }
        }

        $repasExistants = [];
        foreach ($this->repas as $repas) {
            $cle = $repas->getCle();
            if (!isset($repasAttendus[$cle])) {
                $this->repas->removeElement($repas);
                continue;
            }
            $repasExistants[$cle] = true;
        }

        foreach ($repasAttendus as $cle => [$date, $typeRepas]) {
            if (!isset($repasExistants[$cle])) {
                $this->repas->add(new RepasInscription($this, $date, $typeRepas));
            }
        }
    }

    public function supprimer(Utilisateur $auteur): void
    {
        $this->actif = false;
        $this->modifiePar = $auteur;
    }

    /** @param list<string> $repasSelectionnes */
    public function definirRepasSelectionnes(array $repasSelectionnes): void
    {
        $selection = array_fill_keys($repasSelectionnes, true);
        foreach ($this->repas as $repas) {
            $repas->selectionner(isset($selection[$repas->getCle()]));
        }
    }

    /** @return list<string> */
    public function getRepasSelectionnes(): array
    {
        $selectionnes = [];
        foreach ($this->repas as $repas) {
            if ($repas->isSelectionne()) {
                $selectionnes[] = $repas->getCle();
            }
        }

        return $selectionnes;
    }

    private static function genererUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20);
    }

    public function getId(): string { return $this->id; }
    public function getType(): string { return $this->type; }
    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function getThematique(): ?Thematique { return $this->thematique; }
    public function getNomEquipeCompa(): ?string { return $this->nomEquipeCompa; }
    public function getNombrePersonnes(): ?int { return $this->nombrePersonnes; }
    public function getDateDebut(): \DateTimeImmutable { return $this->dateDebut; }
    public function getDateFin(): \DateTimeImmutable { return $this->dateFin; }
    public function getTypeCouchage(): string { return $this->typeCouchage; }
    public function getNombreEnfants(): int { return $this->nombreEnfants; }
    public function getNombreVegetariens(): int { return $this->nombreVegetariens; }
    public function getNombreAllergieOeuf(): int { return $this->nombreAllergieOeuf; }
    public function getNombreAllergieArachide(): int { return $this->nombreAllergieArachide; }
    public function getCommentaire(): ?string { return $this->commentaire; }
    public function isActif(): bool { return $this->actif; }
    public function getNombreRepas(): int { return $this->repas->count(); }
    public function getNombreRepasSelectionnes(): int { return count($this->getRepasSelectionnes()); }
    /** @return Collection<int, RepasInscription> */
    public function getRepas(): Collection { return $this->repas; }
}
