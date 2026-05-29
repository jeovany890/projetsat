<?php

namespace App\Entity;

use App\Repository\CampagnePhishingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CampagnePhishingRepository::class)]
#[ORM\Table(name: "campagne_phishing")]
class CampagnePhishing
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $titre = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $autorisationConfirmee = false;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateAutorisation = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $nomAutorisateur = null;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'PLANIFIEE'])]
    private string $statut = 'PLANIFIEE';

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateTermine = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateCreation = null;

    // ── Relations ──
    #[ORM\ManyToOne(targetEntity: RSSI::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?RSSI $rssi = null;

    #[ORM\ManyToOne(targetEntity: GabaritPhishing::class, inversedBy: 'campagnes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?GabaritPhishing $gabarit = null;

    #[ORM\OneToMany(targetEntity: ResultatPhishing::class, mappedBy: 'campagne', cascade: ['persist', 'remove'])]
    private Collection $resultats;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->resultats    = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(string $titre): static { $this->titre = $titre; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    // ── Autorisation ──
    public function isAutorisationConfirmee(): bool { return $this->autorisationConfirmee; }
    public function getDateAutorisation(): ?\DateTimeInterface { return $this->dateAutorisation; }
    public function getNomAutorisateur(): ?string { return $this->nomAutorisateur; }
    public function confirmerAutorisation(string $nom): static
    {
        $this->autorisationConfirmee = true;
        $this->dateAutorisation      = new \DateTime();
        $this->nomAutorisateur       = $nom;
        return $this;
    }

    // ── Statut & dates ──
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }
    public function getDateDebut(): ?\DateTimeInterface { return $this->dateDebut; }
    public function setDateDebut(?\DateTimeInterface $d): static { $this->dateDebut = $d; return $this; }
    public function getDateTermine(): ?\DateTimeInterface { return $this->dateTermine; }
    public function setDateTermine(?\DateTimeInterface $d): static { $this->dateTermine = $d; return $this; }
    public function getDateCreation(): ?\DateTimeInterface { return $this->dateCreation; }

    // ── Relations ──
    public function getRssi(): ?RSSI { return $this->rssi; }
    public function setRssi(?RSSI $rssi): static { $this->rssi = $rssi; return $this; }

    public function getGabarit(): ?GabaritPhishing { return $this->gabarit; }
    public function setGabarit(?GabaritPhishing $gabarit): static { $this->gabarit = $gabarit; return $this; }

    public function getResultats(): Collection { return $this->resultats; }
    public function addResultat(ResultatPhishing $resultat): static
    {
        if (!$this->resultats->contains($resultat)) {
            $this->resultats->add($resultat);
            $resultat->setCampagne($this);
        }
        return $this;
    }

    public function __toString(): string { return $this->titre ?? ''; }
}