<?php

namespace App\Entity;

use App\Repository\ProgressionModuleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProgressionModuleRepository::class)]
#[ORM\Table(name: "progression_module")]
class ProgressionModule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'NON_COMMENCE'])]
    private string $statut = 'NON_COMMENCE';

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $pourcentageProgression = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $tempsPasseMinutes = 0;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $scoreQuiz = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $pointsGagnes = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $etoilesGagnees = 0;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $echeance = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $estEnRetard = false;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateTermine = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateDernierAcces = null;

    #[ORM\ManyToOne(targetEntity: Employe::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Employe $employe = null;

    #[ORM\ManyToOne(targetEntity: ModuleFormation::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ModuleFormation $module = null;

    #[ORM\ManyToOne(targetEntity: CampagneFormation::class, inversedBy: 'progressions')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?CampagneFormation $campagne = null;

    public function getId(): ?int { return $this->id; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }
    public function getPourcentageProgression(): int { return $this->pourcentageProgression; }
    public function setPourcentageProgression(int $pourcentageProgression): static { $this->pourcentageProgression = $pourcentageProgression; return $this; }
    public function getTempsPasseMinutes(): int { return $this->tempsPasseMinutes; }
    public function setTempsPasseMinutes(int $tempsPasseMinutes): static { $this->tempsPasseMinutes = $tempsPasseMinutes; return $this; }
    public function getScoreQuiz(): ?int { return $this->scoreQuiz; }
    public function setScoreQuiz(?int $scoreQuiz): static { $this->scoreQuiz = $scoreQuiz; return $this; }
    public function getPointsGagnes(): int { return $this->pointsGagnes; }
    public function setPointsGagnes(int $pointsGagnes): static { $this->pointsGagnes = $pointsGagnes; return $this; }
    public function getEtoilesGagnees(): int { return $this->etoilesGagnees; }
    public function setEtoilesGagnees(int $etoilesGagnees): static { $this->etoilesGagnees = $etoilesGagnees; return $this; }
    public function getEcheance(): ?\DateTimeInterface { return $this->echeance; }
    public function setEcheance(?\DateTimeInterface $echeance): static { $this->echeance = $echeance; return $this; }
    public function isEstEnRetard(): bool { return $this->estEnRetard; }
    public function setEstEnRetard(bool $estEnRetard): static { $this->estEnRetard = $estEnRetard; return $this; }
    public function getDateDebut(): ?\DateTimeInterface { return $this->dateDebut; }
    public function setDateDebut(?\DateTimeInterface $dateDebut): static { $this->dateDebut = $dateDebut; return $this; }
    public function getDateTermine(): ?\DateTimeInterface { return $this->dateTermine; }
    public function setDateTermine(?\DateTimeInterface $dateTermine): static { $this->dateTermine = $dateTermine; return $this; }
    public function getDateDernierAcces(): ?\DateTimeInterface { return $this->dateDernierAcces; }
    public function setDateDernierAcces(?\DateTimeInterface $dateDernierAcces): static { $this->dateDernierAcces = $dateDernierAcces; return $this; }
    public function getEmploye(): ?Employe { return $this->employe; }
    public function setEmploye(?Employe $employe): static { $this->employe = $employe; return $this; }
    public function getModule(): ?ModuleFormation { return $this->module; }
    public function setModule(?ModuleFormation $module): static { $this->module = $module; return $this; }
    public function getCampagne(): ?CampagneFormation { return $this->campagne; }
    public function setCampagne(?CampagneFormation $campagne): static { $this->campagne = $campagne; return $this; }
}