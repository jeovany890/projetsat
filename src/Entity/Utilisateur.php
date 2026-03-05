<?php

namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\InheritanceType("SINGLE_TABLE")]
#[ORM\DiscriminatorColumn(name: "type", type: "string")]
#[ORM\DiscriminatorMap([
    "administrateur" => Administrateur::class,
    "rssi" => RSSI::class,
    "employe" => Employe::class
])]
#[ORM\Table(name: "utilisateur")]
abstract class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $motDePasse = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $prenom = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $nom = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $estActif = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $estVerifie = false;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $estPremiereConnexion = true;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $resetPasswordToken = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $resetPasswordExpiration = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateDerniereConnexion = null;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
    }

    // ========================================
    // GETTERS ET SETTERS
    // ========================================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getMotDePasse(): ?string
    {
        return $this->motDePasse;
    }

    public function setMotDePasse(string $motDePasse): static
    {
        $this->motDePasse = $motDePasse;
        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;
        return $this;
    }

    public function isEstActif(): bool
    {
        return $this->estActif;
    }

    public function setEstActif(bool $estActif): static
    {
        $this->estActif = $estActif;
        return $this;
    }

    public function isEstVerifie(): bool
    {
        return $this->estVerifie;
    }

    public function setEstVerifie(bool $estVerifie): static
    {
        $this->estVerifie = $estVerifie;
        return $this;
    }

    public function isEstPremiereConnexion(): bool
    {
        return $this->estPremiereConnexion;
    }

    public function setEstPremiereConnexion(bool $estPremiereConnexion): static
    {
        $this->estPremiereConnexion = $estPremiereConnexion;
        return $this;
    }

    public function getResetPasswordToken(): ?string
    {
        return $this->resetPasswordToken;
    }

    public function setResetPasswordToken(?string $resetPasswordToken): static
    {
        $this->resetPasswordToken = $resetPasswordToken;
        return $this;
    }

    public function getResetPasswordExpiration(): ?\DateTimeInterface
    {
        return $this->resetPasswordExpiration;
    }

    public function setResetPasswordExpiration(?\DateTimeInterface $resetPasswordExpiration): static
    {
        $this->resetPasswordExpiration = $resetPasswordExpiration;
        return $this;
    }

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeInterface $dateCreation): static
    {
        $this->dateCreation = $dateCreation;
        return $this;
    }

    public function getDateDerniereConnexion(): ?\DateTimeInterface
    {
        return $this->dateDerniereConnexion;
    }

    public function setDateDerniereConnexion(?\DateTimeInterface $dateDerniereConnexion): static
    {
        $this->dateDerniereConnexion = $dateDerniereConnexion;
        return $this;
    }

    // ========================================
    // MÉTHODES UserInterface (pour Symfony Security)
    // ========================================

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;

        // Ajouter automatiquement le rôle selon le type
        if ($this instanceof Administrateur) {
            $roles[] = 'ROLE_ADMIN';
        } elseif ($this instanceof RSSI) {
            $roles[] = 'ROLE_RSSI';
        } elseif ($this instanceof Employe) {
            $roles[] = 'ROLE_EMPLOYE';
        }

        // Tout le monde a au moins ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): string
    {
        return $this->motDePasse;
    }

    public function eraseCredentials(): void
    {
        // Nettoyer les données sensibles temporaires si nécessaire
    }

    // ========================================
    // MÉTHODES UTILITAIRES
    // ========================================

    public function getNomComplet(): string
    {
        return $this->prenom . ' ' . $this->nom;
    }

    public function isTokenResetValide(): bool
    {
        if (!$this->resetPasswordToken || !$this->resetPasswordExpiration) {
            return false;
        }

        return $this->resetPasswordExpiration > new \DateTime();
    }
}