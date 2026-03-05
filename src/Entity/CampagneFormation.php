<?php

namespace App\Entity;

use App\Repository\CampagneFormationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CampagneFormationRepository::class)]
#[ORM\Table(name: "campagne_formation")]
class CampagneFormation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $titre = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 10)]
    private ?string $trimestre = null;

    #[ORM\Column(type: 'integer')]
    private ?int $annee = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'PLANIFIEE'])]
    private string $statut = 'PLANIFIEE';

    #[ORM\Column(type: 'integer', options: ['default' => 50])]
    private int $pointsPenalite = 50;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $totalParticipants = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $nombreTermines = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $nombreEnCours = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $nombreEnRetard = 0;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\ManyToOne(targetEntity: RSSI::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?RSSI $rssi = null;

    #[ORM\ManyToMany(targetEntity: ModuleFormation::class)]
    #[ORM\JoinTable(name: 'campagne_formation_module')]
    private Collection $modules;

    #[ORM\OneToMany(targetEntity: ProgressionModule::class, mappedBy: 'campagne', cascade: ['persist', 'remove'])]
    private Collection $progressions;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->modules = new ArrayCollection();
        $this->progressions = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(string $titre): static { $this->titre = $titre; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getTrimestre(): ?string { return $this->trimestre; }
    public function setTrimestre(string $trimestre): static { $this->trimestre = $trimestre; return $this; }
    public function getAnnee(): ?int { return $this->annee; }
    public function setAnnee(int $annee): static { $this->annee = $annee; return $this; }
    public function getDateDebut(): ?\DateTimeInterface { return $this->dateDebut; }
    public function setDateDebut(\DateTimeInterface $dateDebut): static { $this->dateDebut = $dateDebut; return $this; }
    public function getDateFin(): ?\DateTimeInterface { return $this->dateFin; }
    public function setDateFin(\DateTimeInterface $dateFin): static { $this->dateFin = $dateFin; return $this; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }
    public function getPointsPenalite(): int { return $this->pointsPenalite; }
    public function setPointsPenalite(int $pointsPenalite): static { $this->pointsPenalite = $pointsPenalite; return $this; }
    public function getTotalParticipants(): int { return $this->totalParticipants; }
    public function setTotalParticipants(int $totalParticipants): static { $this->totalParticipants = $totalParticipants; return $this; }
    public function getNombreTermines(): int { return $this->nombreTermines; }
    public function setNombreTermines(int $nombreTermines): static { $this->nombreTermines = $nombreTermines; return $this; }
    public function getNombreEnCours(): int { return $this->nombreEnCours; }
    public function setNombreEnCours(int $nombreEnCours): static { $this->nombreEnCours = $nombreEnCours; return $this; }
    public function getNombreEnRetard(): int { return $this->nombreEnRetard; }
    public function setNombreEnRetard(int $nombreEnRetard): static { $this->nombreEnRetard = $nombreEnRetard; return $this; }
    public function getDateCreation(): ?\DateTimeInterface { return $this->dateCreation; }
    public function setDateCreation(\DateTimeInterface $dateCreation): static { $this->dateCreation = $dateCreation; return $this; }
    public function getRssi(): ?RSSI { return $this->rssi; }
    public function setRssi(?RSSI $rssi): static { $this->rssi = $rssi; return $this; }
    public function getModules(): Collection { return $this->modules; }
    public function addModule(ModuleFormation $module): static { if (!$this->modules->contains($module)) { $this->modules->add($module); } return $this; }
    public function removeModule(ModuleFormation $module): static { $this->modules->removeElement($module); return $this; }
    public function getProgressions(): Collection { return $this->progressions; }
    public function addProgression(ProgressionModule $progression): static { if (!$this->progressions->contains($progression)) { $this->progressions->add($progression); $progression->setCampagne($this); } return $this; }
    public function removeProgression(ProgressionModule $progression): static { if ($this->progressions->removeElement($progression)) { if ($progression->getCampagne() === $this) { $progression->setCampagne(null); } } return $this; }
    public function __toString(): string { return $this->titre ?? ''; }
}