<?php

namespace App\Entity;

use App\Repository\SimulationInteractiveRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SimulationInteractiveRepository::class)]
#[ORM\Table(name: "simulation_interactive")]
class SimulationInteractive
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $titre = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $typeSimulation = null;

    #[ORM\Column(type: 'string', length: 20)]
    private ?string $difficulte = null;

    #[ORM\Column(type: 'integer')]
    private ?int $dureeEstimee = null;

    #[ORM\Column(type: 'json')]
    private array $contenuSimulation = [];

    #[ORM\Column(type: 'integer', options: ['default' => 100])]
    private int $pointsReussite = 100;

    #[ORM\Column(type: 'integer', options: ['default' => 70])]
    private int $seuilReussite = 70;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $estPublie = false;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateCreation = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(string $titre): static { $this->titre = $titre; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getTypeSimulation(): ?string { return $this->typeSimulation; }
    public function setTypeSimulation(string $typeSimulation): static { $this->typeSimulation = $typeSimulation; return $this; }
    public function getDifficulte(): ?string { return $this->difficulte; }
    public function setDifficulte(string $difficulte): static { $this->difficulte = $difficulte; return $this; }
    public function getDureeEstimee(): ?int { return $this->dureeEstimee; }
    public function setDureeEstimee(int $dureeEstimee): static { $this->dureeEstimee = $dureeEstimee; return $this; }
    public function getContenuSimulation(): array { return $this->contenuSimulation; }
    public function setContenuSimulation(array $contenuSimulation): static { $this->contenuSimulation = $contenuSimulation; return $this; }
    public function getPointsReussite(): int { return $this->pointsReussite; }
    public function setPointsReussite(int $pointsReussite): static { $this->pointsReussite = $pointsReussite; return $this; }
    public function getSeuilReussite(): int { return $this->seuilReussite; }
    public function setSeuilReussite(int $seuilReussite): static { $this->seuilReussite = $seuilReussite; return $this; }
    public function isEstPublie(): bool { return $this->estPublie; }
    public function setEstPublie(bool $estPublie): static { $this->estPublie = $estPublie; return $this; }
    public function getDateCreation(): ?\DateTimeInterface { return $this->dateCreation; }
    public function setDateCreation(\DateTimeInterface $dateCreation): static { $this->dateCreation = $dateCreation; return $this; }
    public function __toString(): string { return $this->titre ?? ''; }
}