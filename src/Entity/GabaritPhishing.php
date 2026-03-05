<?php

namespace App\Entity;

use App\Repository\GabaritPhishingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GabaritPhishingRepository::class)]
#[ORM\Table(name: "gabarit_phishing")]
class GabaritPhishing
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $titre = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $categorie = null;

    #[ORM\Column(type: 'string', length: 20)]
    private ?string $difficulte = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $compteEmailDsn = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $nomExpediteur = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $emailExpediteur = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $sujetEmail = null;

    #[ORM\Column(type: 'text')]
    private ?string $contenuHtml = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $contenuTexte = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $indicesPieges = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $estActif = true;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $nombreUtilisations = 0;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\ManyToOne(targetEntity: Administrateur::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Administrateur $administrateur = null;

    #[ORM\OneToMany(targetEntity: CampagnePhishing::class, mappedBy: 'gabarit')]
    private Collection $campagnes;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->campagnes = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(string $titre): static { $this->titre = $titre; return $this; }
    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = $slug; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getCategorie(): ?string { return $this->categorie; }
    public function setCategorie(string $categorie): static { $this->categorie = $categorie; return $this; }
    public function getDifficulte(): ?string { return $this->difficulte; }
    public function setDifficulte(string $difficulte): static { $this->difficulte = $difficulte; return $this; }
    public function getCompteEmailDsn(): ?string { return $this->compteEmailDsn; }
    public function setCompteEmailDsn(string $compteEmailDsn): static { $this->compteEmailDsn = $compteEmailDsn; return $this; }
    public function getNomExpediteur(): ?string { return $this->nomExpediteur; }
    public function setNomExpediteur(string $nomExpediteur): static { $this->nomExpediteur = $nomExpediteur; return $this; }
    public function getEmailExpediteur(): ?string { return $this->emailExpediteur; }
    public function setEmailExpediteur(string $emailExpediteur): static { $this->emailExpediteur = $emailExpediteur; return $this; }
    public function getSujetEmail(): ?string { return $this->sujetEmail; }
    public function setSujetEmail(string $sujetEmail): static { $this->sujetEmail = $sujetEmail; return $this; }
    public function getContenuHtml(): ?string { return $this->contenuHtml; }
    public function setContenuHtml(string $contenuHtml): static { $this->contenuHtml = $contenuHtml; return $this; }
    public function getContenuTexte(): ?string { return $this->contenuTexte; }
    public function setContenuTexte(?string $contenuTexte): static { $this->contenuTexte = $contenuTexte; return $this; }
    public function getIndicesPieges(): ?array { return $this->indicesPieges; }
    public function setIndicesPieges(?array $indicesPieges): static { $this->indicesPieges = $indicesPieges; return $this; }
    public function isEstActif(): bool { return $this->estActif; }
    public function setEstActif(bool $estActif): static { $this->estActif = $estActif; return $this; }
    public function getNombreUtilisations(): int { return $this->nombreUtilisations; }
    public function setNombreUtilisations(int $nombreUtilisations): static { $this->nombreUtilisations = $nombreUtilisations; return $this; }
    public function incrementerUtilisations(): static { $this->nombreUtilisations++; return $this; }
    public function getDateCreation(): ?\DateTimeInterface { return $this->dateCreation; }
    public function setDateCreation(\DateTimeInterface $dateCreation): static { $this->dateCreation = $dateCreation; return $this; }
    public function getAdministrateur(): ?Administrateur { return $this->administrateur; }
    public function setAdministrateur(?Administrateur $administrateur): static { $this->administrateur = $administrateur; return $this; }
    public function getCampagnes(): Collection { return $this->campagnes; }
    public function addCampagne(CampagnePhishing $campagne): static { if (!$this->campagnes->contains($campagne)) { $this->campagnes->add($campagne); $campagne->setGabarit($this); } return $this; }
    public function removeCampagne(CampagnePhishing $campagne): static { if ($this->campagnes->removeElement($campagne)) { if ($campagne->getGabarit() === $this) { $campagne->setGabarit(null); } } return $this; }
    public function __toString(): string { return $this->titre ?? ''; }
}