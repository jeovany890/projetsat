<?php

namespace App\Entity;

use App\Repository\ResultatSimulationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ResultatSimulationRepository::class)]
#[ORM\Table(name: "resultat_simulation")]
class ResultatSimulation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    private ?int $score = null;

    #[ORM\Column(type: 'integer')]
    private ?int $nombreReponsesCorrectes = null;

    #[ORM\Column(type: 'integer')]
    private ?int $nombreTotalQuestions = null;

    #[ORM\Column(type: 'json')]
    private array $reponses = [];

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $aReussi = false;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $pointsGagnes = 0;

    #[ORM\Column(type: 'integer')]
    private ?int $tempsPasseSecondes = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateTermine = null;

    #[ORM\ManyToOne(targetEntity: Employe::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Employe $employe = null;

    #[ORM\ManyToOne(targetEntity: SimulationInteractive::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?SimulationInteractive $simulation = null;

    public function getId(): ?int { return $this->id; }
    public function getScore(): ?int { return $this->score; }
    public function setScore(int $score): static { $this->score = $score; return $this; }
    public function getNombreReponsesCorrectes(): ?int { return $this->nombreReponsesCorrectes; }
    public function setNombreReponsesCorrectes(int $nombreReponsesCorrectes): static { $this->nombreReponsesCorrectes = $nombreReponsesCorrectes; return $this; }
    public function getNombreTotalQuestions(): ?int { return $this->nombreTotalQuestions; }
    public function setNombreTotalQuestions(int $nombreTotalQuestions): static { $this->nombreTotalQuestions = $nombreTotalQuestions; return $this; }
    public function getReponses(): array { return $this->reponses; }
    public function setReponses(array $reponses): static { $this->reponses = $reponses; return $this; }
    public function isAReussi(): bool { return $this->aReussi; }
    public function setAReussi(bool $aReussi): static { $this->aReussi = $aReussi; return $this; }
    public function getPointsGagnes(): int { return $this->pointsGagnes; }
    public function setPointsGagnes(int $pointsGagnes): static { $this->pointsGagnes = $pointsGagnes; return $this; }
    public function getTempsPasseSecondes(): ?int { return $this->tempsPasseSecondes; }
    public function setTempsPasseSecondes(int $tempsPasseSecondes): static { $this->tempsPasseSecondes = $tempsPasseSecondes; return $this; }
    public function getDateDebut(): ?\DateTimeInterface { return $this->dateDebut; }
    public function setDateDebut(\DateTimeInterface $dateDebut): static { $this->dateDebut = $dateDebut; return $this; }
    public function getDateTermine(): ?\DateTimeInterface { return $this->dateTermine; }
    public function setDateTermine(\DateTimeInterface $dateTermine): static { $this->dateTermine = $dateTermine; return $this; }
    public function getEmploye(): ?Employe { return $this->employe; }
    public function setEmploye(?Employe $employe): static { $this->employe = $employe; return $this; }
    public function getSimulation(): ?SimulationInteractive { return $this->simulation; }
    public function setSimulation(?SimulationInteractive $simulation): static { $this->simulation = $simulation; return $this; }
}