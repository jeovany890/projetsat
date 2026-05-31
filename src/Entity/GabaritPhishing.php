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

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $compteEmailDsn = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $nomExpediteur = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $emailExpediteur = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $sujetEmail = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $contenuHtml = null;

    /**
     * Chemin relatif du logo propre à ce gabarit.
     * Ex : "uploads/logos_gabarits/boa.jpeg"
     *      "uploads/logos_gabarits/mtn.png"
     *
     * Stocké en BD, le fichier est uploadé dans public/.
     * L'URL publique = APP_URL + '/' + logoPath.
     *
     * Si null → PhishingService utilise le logo par défaut
     * (public/images/logo.jpeg).
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $logoPath = null;

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
        $this->campagnes    = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(string $titre): static { $this->titre = $titre; return $this; }

    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = $slug; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): static { $this->description = $d; return $this; }

    public function getCategorie(): ?string { return $this->categorie; }
    public function setCategorie(string $categorie): static { $this->categorie = $categorie; return $this; }

    public function getDifficulte(): ?string { return $this->difficulte; }
    public function setDifficulte(string $difficulte): static { $this->difficulte = $difficulte; return $this; }

    public function getCompteEmailDsn(): ?string { return $this->compteEmailDsn; }
    public function setCompteEmailDsn(?string $dsn): static { $this->compteEmailDsn = $dsn; return $this; }

    public function getNomExpediteur(): ?string { return $this->nomExpediteur; }
    public function setNomExpediteur(?string $n): static { $this->nomExpediteur = $n; return $this; }

    public function getEmailExpediteur(): ?string { return $this->emailExpediteur; }
    public function setEmailExpediteur(?string $e): static { $this->emailExpediteur = $e; return $this; }

    public function getSujetEmail(): ?string { return $this->sujetEmail; }
    public function setSujetEmail(?string $s): static { $this->sujetEmail = $s; return $this; }

    public function getContenuHtml(): ?string { return $this->contenuHtml; }
    public function setContenuHtml(?string $c): static { $this->contenuHtml = $c; return $this; }

    public function getLogoPath(): ?string { return $this->logoPath; }
    public function setLogoPath(?string $logoPath): static { $this->logoPath = $logoPath; return $this; }

    public function getIndicesPieges(): ?array { return $this->indicesPieges; }
    public function setIndicesPieges(?array $i): static { $this->indicesPieges = $i; return $this; }

    public function isEstActif(): bool { return $this->estActif; }
    public function setEstActif(bool $estActif): static { $this->estActif = $estActif; return $this; }

    public function getNombreUtilisations(): int { return $this->nombreUtilisations; }
    public function incrementerUtilisations(): static { $this->nombreUtilisations++; return $this; }

    public function getDateCreation(): ?\DateTimeInterface { return $this->dateCreation; }

    public function getAdministrateur(): ?Administrateur { return $this->administrateur; }
    public function setAdministrateur(?Administrateur $a): static { $this->administrateur = $a; return $this; }

    public function getCampagnes(): Collection { return $this->campagnes; }

    /**
     * Retourne l'URL publique complète du logo de ce gabarit.
     * Utilisé dans PhishingService pour construire le src de l'image.
     */
    public function getLogoUrl(string $baseUrl): ?string
    {
        if (!$this->logoPath) return null;
        return rtrim($baseUrl, '/') . '/' . ltrim($this->logoPath, '/');
    }

    public function __toString(): string { return $this->titre ?? ''; }
}