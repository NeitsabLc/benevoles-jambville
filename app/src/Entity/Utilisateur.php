<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\Table(name: 'utilisateur', schema: 'benevole_jambville')]
final class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\Column(name: 'code_adherent', length: 50)]
    private string $codeAdherent;

    #[ORM\Column(length: 100)]
    private string $nom;

    #[ORM\Column(length: 100)]
    private string $prenom;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column]
    private bool $vegetarien = false;

    #[ORM\Column(name: 'allergie_oeuf')]
    private bool $allergieOeuf = false;

    #[ORM\Column(name: 'allergie_arachide')]
    private bool $allergieArachide = false;

    #[ORM\Column(name: 'regime_autre', type: 'text', nullable: true)]
    private ?string $regimeAutre = null;

    #[ORM\Column(name: 'besoin_couchage', type: 'text', nullable: true)]
    private ?string $besoinCouchage = null;

    #[ORM\Column(name: 'foulard_remis')]
    private bool $foulardRemis = false;

    #[ORM\Column(name: 'tenue_remise')]
    private bool $tenueRemise = false;

    #[ORM\Column(name: 'telephone_modifie_localement')]
    private bool $telephoneModifieLocalement = false;

    #[ORM\Column(length: 30)]
    private string $role = 'BENEVOLE';

    #[ORM\Column(name: 'mot_de_passe', length: 255, nullable: true)]
    private ?string $motDePasse = null;

    #[ORM\Column(name: 'changement_mot_de_passe_requis')]
    private bool $changementMotDePasseRequis = false;

    #[ORM\Column(name: 'jeton_activation', length: 64, nullable: true)]
    private ?string $jetonActivation = null;

    #[ORM\Column(name: 'expiration_jeton_activation', type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $expirationJetonActivation = null;

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\Column(name: 'desactive_le', type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $desactiveLe = null;

    public function getId(): string
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): ?string
    {
        return $this->motDePasse;
    }

    public function setPassword(string $motDePasse): void
    {
        $this->motDePasse = $motDePasse;
    }

    public function getRoles(): array
    {
        return ['ROLE_'.$this->role];
    }

    public function eraseCredentials(): void
    {
    }

    public function getNomComplet(): string
    {
        return $this->prenom.' '.$this->nom;
    }

    public function getPrenom(): string
    {
        return $this->prenom;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function getCodeAdherent(): string
    {
        return $this->codeAdherent;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function isVegetarien(): bool
    {
        return $this->vegetarien;
    }

    public function hasAllergieOeuf(): bool
    {
        return $this->allergieOeuf;
    }

    public function hasAllergieArachide(): bool
    {
        return $this->allergieArachide;
    }

    public function getRegimeAutre(): ?string
    {
        return $this->regimeAutre;
    }

    public function getBesoinCouchage(): ?string
    {
        return $this->besoinCouchage;
    }

    public function isFoulardRemis(): bool
    {
        return $this->foulardRemis;
    }

    public function isTenueRemise(): bool
    {
        return $this->tenueRemise;
    }

    public function modifierProfil(
        ?string $telephone,
        bool $vegetarien,
        bool $allergieOeuf,
        bool $allergieArachide,
        ?string $regimeAutre,
        ?string $besoinCouchage,
    ): void {
        if ($this->telephone !== $telephone) {
            $this->telephoneModifieLocalement = true;
        }
        $this->telephone = $telephone;
        $this->vegetarien = $vegetarien;
        $this->allergieOeuf = $allergieOeuf;
        $this->allergieArachide = $allergieArachide;
        $this->regimeAutre = $regimeAutre;
        $this->besoinCouchage = $besoinCouchage;
    }

    public function modifierRemiseEquipement(bool $foulardRemis, bool $tenueRemise): void
    {
        $this->foulardRemis = $foulardRemis;
        $this->tenueRemise = $tenueRemise;
    }

    public function getRoleMetier(): string
    {
        return $this->role;
    }

    public function isEquipePilote(): bool
    {
        return $this->role === 'EQUIPE_PILOTE';
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function basculerActivation(?\DateTimeImmutable $maintenant = null): void
    {
        $this->actif = !$this->actif;

        if (!$this->actif) {
            $this->desactiveLe = $maintenant ?? new \DateTimeImmutable();
            $this->allergieOeuf = false;
            $this->allergieArachide = false;
            $this->regimeAutre = null;
        } else {
            $this->desactiveLe = null;
        }
    }

    public function getDesactiveLe(): ?\DateTimeImmutable
    {
        return $this->desactiveLe;
    }

    public function isChangementMotDePasseRequis(): bool
    {
        return $this->changementMotDePasseRequis;
    }

    public function terminerActivation(): void
    {
        $this->changementMotDePasseRequis = false;
        $this->jetonActivation = null;
        $this->expirationJetonActivation = null;
    }

    /**
     * Prépare une première connexion et retourne le jeton à transmettre à
     * l'utilisateur. Seule son empreinte est conservée en base de données.
     */
    public function preparerActivation(\DateTimeImmutable $expiration): string
    {
        $token = bin2hex(random_bytes(32));
        $this->jetonActivation = hash('sha256', $token);
        $this->expirationJetonActivation = $expiration;
        $this->changementMotDePasseRequis = true;

        return $token;
    }

    public function activationEstValideA(\DateTimeImmutable $date): bool
    {
        return $this->jetonActivation !== null
            && $this->expirationJetonActivation !== null
            && $this->expirationJetonActivation >= $date
            && $this->actif;
    }
}
