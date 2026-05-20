<?php

namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\InheritanceType("SINGLE_TABLE")]
#[ORM\DiscriminatorColumn(name: "role", type: "string")]
#[ORM\DiscriminatorMap([
    "administrateur" => Administrateur::class,
    "rssi"           => RSSI::class,
    "employe"        => Employe::class,
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

    // La colonne JSON "roles" est supprimée.
    // Le rôle est déduit automatiquement par instanceof dans getRoles().
    // La colonne "role" (ex "type") reste en base comme discriminateur Doctrine.

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function getMotDePasse(): ?string { return $this->motDePasse; }
    public function setMotDePasse(string $motDePasse): static { $this->motDePasse = $motDePasse; return $this; }

    public function getPrenom(): ?string { return $this->prenom; }
    public function setPrenom(string $prenom): static { $this->prenom = $prenom; return $this; }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }

    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(?string $telephone): static { $this->telephone = $telephone; return $this; }

    public function isEstActif(): bool { return $this->estActif; }
    public function setEstActif(bool $estActif): static { $this->estActif = $estActif; return $this; }

    public function isEstVerifie(): bool { return $this->estVerifie; }
    public function setEstVerifie(bool $estVerifie): static { $this->estVerifie = $estVerifie; return $this; }

    public function isEstPremiereConnexion(): bool { return $this->estPremiereConnexion; }
    public function setEstPremiereConnexion(bool $v): static { $this->estPremiereConnexion = $v; return $this; }

    public function getResetPasswordToken(): ?string { return $this->resetPasswordToken; }
    public function setResetPasswordToken(?string $t): static { $this->resetPasswordToken = $t; return $this; }

    public function getResetPasswordExpiration(): ?\DateTimeInterface { return $this->resetPasswordExpiration; }
    public function setResetPasswordExpiration(?\DateTimeInterface $d): static { $this->resetPasswordExpiration = $d; return $this; }

    public function getDateCreation(): ?\DateTimeInterface { return $this->dateCreation; }
    public function setDateCreation(\DateTimeInterface $d): static { $this->dateCreation = $d; return $this; }

    public function getDateDerniereConnexion(): ?\DateTimeInterface { return $this->dateDerniereConnexion; }
    public function setDateDerniereConnexion(?\DateTimeInterface $d): static { $this->dateDerniereConnexion = $d; return $this; }

    // ── Symfony Security ──

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * Rôle calculé depuis le type réel de l'objet (instanceof).
     * Aucune colonne "roles" en base — zéro redondance.
     */
    public function getRoles(): array
    {
        if ($this instanceof Administrateur) return ['ROLE_ADMIN', 'ROLE_USER'];
        if ($this instanceof RSSI)           return ['ROLE_RSSI',  'ROLE_USER'];
        if ($this instanceof Employe)        return ['ROLE_EMPLOYE', 'ROLE_USER'];
        return ['ROLE_USER'];
    }

    public function getPassword(): string { return $this->motDePasse; }

    public function eraseCredentials(): void {}

    // ── Utilitaires ──

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