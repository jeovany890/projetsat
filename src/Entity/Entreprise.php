<?php

namespace App\Entity;

use App\Repository\EntrepriseRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: EntrepriseRepository::class)]
#[ORM\Table(name: "entreprise")]
class Entreprise
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $nom = null;

    #[ORM\Column(type: 'string', length: 13, unique: true)]
    private ?string $ifu = null;
#[ORM\Column(type: 'boolean', options: ['default' => false])]
private bool $charteAcceptee = false;

   #[ORM\Column(type: 'datetime', nullable: true)]
 private ?\DateTimeInterface $dateAcceptationCharte = null;
    #[ORM\Column(type: 'string', length: 50)]
    private ?string $rccm = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $secteur = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $nombreEmployes = 0;

    #[ORM\Column(type: 'string', length: 20)]
    private ?string $telephone = null;

    #[ORM\Column(type: 'string', length: 180)]
    private ?string $email = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'EN_ATTENTE'])]
    private string $statut = 'EN_ATTENTE';

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateValidation = null;

    // Relations
    #[ORM\OneToMany(targetEntity: Departement::class, mappedBy: 'entreprise', cascade: ['persist', 'remove'])]
    private Collection $departements;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->departements = new ArrayCollection();
    }

    // ========================================
    // GETTERS ET SETTERS
    // ========================================

    public function getId(): ?int
    {
        return $this->id;
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

    public function getIfu(): ?string
    {
        return $this->ifu;
    }

    public function setIfu(string $ifu): static
    {
        $this->ifu = $ifu;
        return $this;
    }

    public function getRccm(): ?string
    {
        return $this->rccm;
    }

    public function setRccm(string $rccm): static
    {
        $this->rccm = $rccm;
        return $this;
    }
    public function isCharteAcceptee(): bool
{
    return $this->charteAcceptee;
}

public function setCharteAcceptee(bool $charteAcceptee): static
{
    $this->charteAcceptee = $charteAcceptee;
    return $this;
}

public function getDateAcceptationCharte(): ?\DateTimeInterface
{
    return $this->dateAcceptationCharte;
}

public function setDateAcceptationCharte(?\DateTimeInterface $date): static
{
    $this->dateAcceptationCharte = $date;
    return $this;
}

// Méthode utilitaire
public function accepterCharte(): static
{
    $this->charteAcceptee = true;
    $this->dateAcceptationCharte = new \DateTime();
    return $this;
}

    public function getSecteur(): ?string
    {
        return $this->secteur;
    }

    public function setSecteur(string $secteur): static
    {
        $this->secteur = $secteur;
        return $this;
    }

    public function getNombreEmployes(): int
    {
        return $this->nombreEmployes;
    }

    public function setNombreEmployes(int $nombreEmployes): static
    {
        $this->nombreEmployes = $nombreEmployes;
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(string $telephone): static
    {
        $this->telephone = $telephone;
        return $this;
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

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;
        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
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

    public function getDateValidation(): ?\DateTimeInterface
    {
        return $this->dateValidation;
    }

    public function setDateValidation(?\DateTimeInterface $dateValidation): static
    {
        $this->dateValidation = $dateValidation;
        return $this;
    }

    // ========================================
    // RELATIONS
    // ========================================

    /**
     * @return Collection<int, Departement>
     */
    public function getDepartements(): Collection
    {
        return $this->departements;
    }

    public function addDepartement(Departement $departement): static
    {
        if (!$this->departements->contains($departement)) {
            $this->departements->add($departement);
            $departement->setEntreprise($this);
        }

        return $this;
    }

    public function removeDepartement(Departement $departement): static
    {
        if ($this->departements->removeElement($departement)) {
            if ($departement->getEntreprise() === $this) {
                $departement->setEntreprise(null);
            }
        }

        return $this;
    }

    // ========================================
    // MÉTHODES UTILITAIRES
    // ========================================

    public function isValidee(): bool
    {
        return $this->statut === 'ACTIF';
    }

    public function isEnAttente(): bool
    {
        return $this->statut === 'EN_ATTENTE';
    }

    public function isSuspendue(): bool
    {
        return $this->statut === 'SUSPENDU';
    }

    public function isRejetee(): bool
    {
        return $this->statut === 'REJETE';
    }

    public function valider(): static
    {
        $this->statut = 'ACTIF';
        $this->dateValidation = new \DateTime();
        return $this;
    }

    public function suspendre(): static
    {
        $this->statut = 'SUSPENDU';
        return $this;
    }

    public function rejeter(): static
    {
        $this->statut = 'REJETE';
        return $this;
    }

    public function reactiver(): static
    {
        $this->statut = 'ACTIF';
        return $this;
    }

    public function __toString(): string
    {
        return $this->nom ?? '';
    }
}