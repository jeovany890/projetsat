<?php

namespace App\Entity;

use App\Repository\ModuleFormationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ModuleFormationRepository::class)]
#[ORM\Table(name: 'module_formation')]
class ModuleFormation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $titre = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: 'text')]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $typeModule = null;

    #[ORM\Column(type: 'string', length: 20)]
    private ?string $difficulte = null;

    #[ORM\Column(type: 'integer')]
    private ?int $dureeEstimee = null;

    #[ORM\Column(type: 'integer', options: ['default' => 100])]
    private int $pointsReussite = 100;

    // Simulation intégrée (plus de table séparée)
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $simulationTitre = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $simulationType = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $simulationContenu = [];

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $estPublie = false;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $categorie = null;

    #[ORM\OneToMany(targetEntity: Chapitre::class, mappedBy: 'module', cascade: ['persist', 'remove'])]
    private Collection $chapitres;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->chapitres = new ArrayCollection();
    }

    // ========================================
    // Getters / Setters
    // ========================================

    public function getId(): ?int { return $this->id; }

    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(string $titre): static { $this->titre = $titre; return $this; }

    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = $slug; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(string $description): static { $this->description = $description; return $this; }

    public function getTypeModule(): ?string { return $this->typeModule; }
    public function setTypeModule(string $typeModule): static { $this->typeModule = $typeModule; return $this; }

    public function getDifficulte(): ?string { return $this->difficulte; }
    public function setDifficulte(string $difficulte): static { $this->difficulte = $difficulte; return $this; }

    public function getDureeEstimee(): ?int { return $this->dureeEstimee; }
    public function setDureeEstimee(int $dureeEstimee): static { $this->dureeEstimee = $dureeEstimee; return $this; }

    public function getPointsReussite(): int { return $this->pointsReussite; }
    public function setPointsReussite(int $points): static { $this->pointsReussite = max(0, $points); return $this; }

    // Simulation
    public function getSimulationTitre(): ?string { return $this->simulationTitre; }
    public function setSimulationTitre(?string $titre): static { $this->simulationTitre = $titre; return $this; }

    public function getSimulationType(): ?string { return $this->simulationType; }
    public function setSimulationType(?string $type): static { $this->simulationType = $type; return $this; }

    public function getSimulationContenu(): ?array { return $this->simulationContenu; }
    public function setSimulationContenu(?array $contenu): static { $this->simulationContenu = $contenu; return $this; }

    public function isEstPublie(): bool { return $this->estPublie; }
    public function setEstPublie(bool $estPublie): static { $this->estPublie = $estPublie; return $this; }

    public function getDateCreation(): ?\DateTimeInterface { return $this->dateCreation; }
    public function setDateCreation(\DateTimeInterface $dateCreation): static { $this->dateCreation = $dateCreation; return $this; }

    public function getCategorie(): ?string { return $this->categorie; }
    public function setCategorie(?string $categorie): static { $this->categorie = $categorie; return $this; }

    public function getChapitres(): Collection { return $this->chapitres; }
    

public function getTotalPointsQuiz(): int
{
    $total = 0;
    foreach ($this->chapitres as $chapitre) {
        $total += $chapitre->getTotalPoints();  // ✅ Correction ici
    }
    return $total;
}
    public function addChapitre(Chapitre $chapitre): static
    {
        if (!$this->chapitres->contains($chapitre)) {
            $this->chapitres->add($chapitre);
            $chapitre->setModule($this);
        }
        return $this;
    }
    public function removeChapitre(Chapitre $chapitre): static
    {
        if ($this->chapitres->removeElement($chapitre) && $chapitre->getModule() === $this) {
            $chapitre->setModule(null);
        }
        return $this;
    }

    // ========================================
    // Méthodes calculées (pas de stockage)
    // ========================================

    public function hasSimulation(): bool
    {
        return !empty($this->simulationTitre) && !empty($this->simulationContenu);
    }

    /**
     * Somme des points de tous les quiz des chapitres
     */


    /**
     * Points attribués à la simulation (total du module - somme des quiz)
     */
    public function getPointsSimulation(): int
    {
        if (!$this->hasSimulation()) {
            return 0;
        }
        return max(0, $this->pointsReussite - $this->getTotalPointsQuiz());
    }

    /**
     * Nombre total de questions du quiz (utile pour l'affichage)
     */
    public function getNombreQuestionsSimulation(): int
    {
        return count($this->simulationContenu ?? []);
    }

    public function __toString(): string { return $this->titre ?? ''; }
    
}
