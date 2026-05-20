<?php

namespace App\Entity;

use App\Repository\ModuleFormationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ModuleFormationRepository::class)]
#[ORM\Table(name: "module_formation")]
class ModuleFormation
{
    /**
     * Bonus de complétion de module par défaut : 20 points.
     *
     * Architecture module standard (100 pts totaux) :
     *   9 quiz × 5 pts             = 45 pts  (Quiz.points)
     *   simulation finale          = 35 pts  (SimulationInteractive.pointsReussite)
     *   bonus complétion (ce champ) = 20 pts
     *                              ─────────
     *                              = 100 pts
     */
    const POINTS_BONUS_DEFAUT = 20;

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

    /**
     * Bonus de complétion attribué à l'employé quand il termine le module
     * (tous chapitres validés + simulation réussie si présente).
     *
     * Ce champ remplace l'ancien "pointsReussite" pour clarifier sa sémantique :
     * il s'agit d'un bonus de fin de module, pas du total des points du module.
     *
     * Anciennement : $pointsReussite (default 100) — valeur trop haute
     * qui s'ajoutait en double aux points déjà octroyés par les quiz et simulation.
     */
    #[ORM\Column(type: 'integer', options: ['default' => 20])]
    private int $pointsBonus = self::POINTS_BONUS_DEFAUT;



    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $estPublie = false;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $categorie = null;

    #[ORM\OneToMany(targetEntity: Chapitre::class, mappedBy: 'module', cascade: ['persist', 'remove'])]
    private Collection $chapitres;

    #[ORM\OneToOne(targetEntity: SimulationInteractive::class, inversedBy: 'module', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'simulation_id', referencedColumnName: 'id', nullable: true)]
    private ?SimulationInteractive $simulation = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->chapitres = new ArrayCollection();
    }

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

    /**
     * Bonus de complétion attribué à la fin du module (en points pédagogiques).
     * Valeur cible : 20 pts pour un module standard de 100 pts.
     */
    public function getPointsBonus(): int { return $this->pointsBonus; }
    public function setPointsBonus(int $pointsBonus): static { $this->pointsBonus = max(0, $pointsBonus); return $this; }

    /**
     * Alias de rétrocompatibilité pour getPointsBonus().
     * @deprecated Utiliser getPointsBonus()
     */
    public function getPointsReussite(): int { return $this->pointsBonus; }

    /**
     * Alias de rétrocompatibilité pour setPointsBonus().
     * @deprecated Utiliser setPointsBonus()
     */


    public function isEstPublie(): bool { return $this->estPublie; }
    public function setEstPublie(bool $estPublie): static { $this->estPublie = $estPublie; return $this; }
    public function getDateCreation(): ?\DateTimeInterface { return $this->dateCreation; }
    public function setDateCreation(\DateTimeInterface $dateCreation): static { $this->dateCreation = $dateCreation; return $this; }
    public function getCategorie(): ?string { return $this->categorie; }
    public function setCategorie(?string $categorie): static { $this->categorie = $categorie; return $this; }

    public function getChapitres(): Collection { return $this->chapitres; }
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
        if ($this->chapitres->removeElement($chapitre)) {
            if ($chapitre->getModule() === $this) {
                $chapitre->setModule(null);
            }
        }
        return $this;
    }
    public function getSimulation(): ?SimulationInteractive { return $this->simulation; }
    public function setSimulation(?SimulationInteractive $simulation): static { $this->simulation = $simulation; return $this; }
    public function __toString(): string { return $this->titre ?? ''; }
}