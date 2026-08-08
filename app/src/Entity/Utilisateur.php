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
