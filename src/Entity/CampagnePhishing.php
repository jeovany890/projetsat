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

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $datePlanifiee = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateTermine = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $totalCibles = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $emailsEnvoyes = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $emailsOuverts = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $liensCliques = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $donneesSubmises = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $emailsSignales = 0;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateCreation = null;

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
        $this->resultats = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(string $titre): static { $this->titre = $titre; return $this; }


public function isAutorisationConfirmee(): bool
{
    return $this->autorisationConfirmee;
}

public function setAutorisationConfirmee(bool $autorisationConfirmee): static
{
    $this->autorisationConfirmee = $autorisationConfirmee;
    return $this;
}

public function getDateAutorisation(): ?\DateTimeInterface
{
    return $this->dateAutorisation;
}

public function setDateAutorisation(?\DateTimeInterface $date): static
{
    $this->dateAutorisation = $date;
    return $this;
}

public function getNomAutorisateur(): ?string
{
    return $this->nomAutorisateur;
}

public function setNomAutorisateur(?string $nom): static
{
    $this->nomAutorisateur = $nom;
    return $this;
}

// Méthode utilitaire
public function confirmerAutorisation(string $nomAutorisateur): static
{
    $this->autorisationConfirmee = true;
    $this->dateAutorisation      = new \DateTime();
    $this->nomAutorisateur       = $nomAutorisateur;
    return $this;
}
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }
    public function getDatePlanifiee(): ?\DateTimeInterface { return $this->datePlanifiee; }
    public function setDatePlanifiee(\DateTimeInterface $datePlanifiee): static { $this->datePlanifiee = $datePlanifiee; return $this; }
    public function getDateDebut(): ?\DateTimeInterface { return $this->dateDebut; }
    public function setDateDebut(?\DateTimeInterface $dateDebut): static { $this->dateDebut = $dateDebut; return $this; }
    public function getDateTermine(): ?\DateTimeInterface { return $this->dateTermine; }
    public function setDateTermine(?\DateTimeInterface $dateTermine): static { $this->dateTermine = $dateTermine; return $this; }
    public function getTotalCibles(): int { return $this->totalCibles; }
    public function setTotalCibles(int $totalCibles): static { $this->totalCibles = $totalCibles; return $this; }
    public function getEmailsEnvoyes(): int { return $this->emailsEnvoyes; }
    public function setEmailsEnvoyes(int $emailsEnvoyes): static { $this->emailsEnvoyes = $emailsEnvoyes; return $this; }
    public function incrementerEmailsEnvoyes(): static { $this->emailsEnvoyes++; return $this; }
    public function getEmailsOuverts(): int { return $this->emailsOuverts; }
    public function setEmailsOuverts(int $emailsOuverts): static { $this->emailsOuverts = $emailsOuverts; return $this; }
    public function incrementerEmailsOuverts(): static { $this->emailsOuverts++; return $this; }
    public function getLiensCliques(): int { return $this->liensCliques; }
    public function setLiensCliques(int $liensCliques): static { $this->liensCliques = $liensCliques; return $this; }
    public function incrementerLiensCliques(): static { $this->liensCliques++; return $this; }
    public function getDonneesSubmises(): int { return $this->donneesSubmises; }
    public function setDonneesSubmises(int $donneesSubmises): static { $this->donneesSubmises = $donneesSubmises; return $this; }
    public function incrementerDonneesSubmises(): static { $this->donneesSubmises++; return $this; }
    public function getEmailsSignales(): int { return $this->emailsSignales; }
    public function setEmailsSignales(int $emailsSignales): static { $this->emailsSignales = $emailsSignales; return $this; }
    public function incrementerEmailsSignales(): static { $this->emailsSignales++; return $this; }
    public function getDateCreation(): ?\DateTimeInterface { return $this->dateCreation; }
    public function setDateCreation(\DateTimeInterface $dateCreation): static { $this->dateCreation = $dateCreation; return $this; }
    public function getRssi(): ?RSSI { return $this->rssi; }
    public function setRssi(?RSSI $rssi): static { $this->rssi = $rssi; return $this; }
    public function getGabarit(): ?GabaritPhishing { return $this->gabarit; }
    public function setGabarit(?GabaritPhishing $gabarit): static { $this->gabarit = $gabarit; return $this; }
    public function getResultats(): Collection { return $this->resultats; }
    public function addResultat(ResultatPhishing $resultat): static { if (!$this->resultats->contains($resultat)) { $this->resultats->add($resultat); $resultat->setCampagne($this); } return $this; }
    public function removeResultat(ResultatPhishing $resultat): static { if ($this->resultats->removeElement($resultat)) { if ($resultat->getCampagne() === $this) { $resultat->setCampagne(null); } } return $this; }
    
    public function getTauxOuverture(): float { return $this->emailsEnvoyes > 0 ? ($this->emailsOuverts / $this->emailsEnvoyes) * 100 : 0; }
    public function getTauxClic(): float { return $this->emailsEnvoyes > 0 ? ($this->liensCliques / $this->emailsEnvoyes) * 100 : 0; }
    public function getTauxSignalement(): float { return $this->emailsEnvoyes > 0 ? ($this->emailsSignales / $this->emailsEnvoyes) * 100 : 0; }
    
    public function __toString(): string { return $this->titre ?? ''; }
}