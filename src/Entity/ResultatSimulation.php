<?php

namespace App\Entity;

use App\Repository\ResultatSimulationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ResultatSimulationRepository::class)]
#[ORM\Table(name: 'resultat_simulation')]
class ResultatSimulation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    private ?int $reponsesCorrectes = null;

    #[ORM\Column(type: 'json')]
    private array $reponses = [];

    #[ORM\Column(type: 'integer')]
    private ?int $tempsPasseSecondes = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateTermine = null;

    // Relations
    #[ORM\ManyToOne(targetEntity: Employe::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Employe $employe = null;

    #[ORM\ManyToOne(targetEntity: ModuleFormation::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ModuleFormation $module = null;

    #[ORM\ManyToOne(targetEntity: ProgressionModule::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ProgressionModule $progression = null;

    // ========================================
    // Getters / Setters
    // ========================================

    public function getId(): ?int { return $this->id; }

    public function getReponsesCorrectes(): ?int { return $this->reponsesCorrectes; }
    public function setReponsesCorrectes(int $reponsesCorrectes): static { $this->reponsesCorrectes = $reponsesCorrectes; return $this; }

    public function getReponses(): array { return $this->reponses; }
    public function setReponses(array $reponses): static { $this->reponses = $reponses; return $this; }

    public function getTempsPasseSecondes(): ?int { return $this->tempsPasseSecondes; }
    public function setTempsPasseSecondes(int $tempsPasseSecondes): static { $this->tempsPasseSecondes = $tempsPasseSecondes; return $this; }

    public function getDateDebut(): ?\DateTimeInterface { return $this->dateDebut; }
    public function setDateDebut(\DateTimeInterface $dateDebut): static { $this->dateDebut = $dateDebut; return $this; }

    public function getDateTermine(): ?\DateTimeInterface { return $this->dateTermine; }
    public function setDateTermine(\DateTimeInterface $dateTermine): static { $this->dateTermine = $dateTermine; return $this; }

    public function getEmploye(): ?Employe { return $this->employe; }
    public function setEmploye(?Employe $employe): static { $this->employe = $employe; return $this; }

    public function getModule(): ?ModuleFormation { return $this->module; }
    public function setModule(?ModuleFormation $module): static { $this->module = $module; return $this; }

    public function getProgression(): ?ProgressionModule { return $this->progression; }
    public function setProgression(?ProgressionModule $progression): static { $this->progression = $progression; return $this; }

    // ========================================
    // Méthodes calculées (pas de stockage)
    // ========================================

    public function getTotalQuestions(): int
    {
        return $this->module?->getNombreQuestionsSimulation() ?? 0;
    }

    public function getScore(): float
    {
        $total = $this->getTotalQuestions();
        if ($total === 0) {
            return 0.0;
        }
        return round(($this->reponsesCorrectes / $total) * 100, 2);
    }

    /**
     * On considère que la simulation est réussie si le score est >= 80% (peut être ajusté)
     * ou vous pouvez stocker un seuil dans ModuleFormation.
     */
    public function isAReussi(): bool
    {
        // Seuil par défaut 80%
        $seuil = 80;
        // Optionnel : si le module a un champ simulationScoreMinimum, l'utiliser
        // $seuil = $this->module?->getSimulationScoreMinimum() ?? 80;
        return $this->getScore() >= $seuil;
    }

    public function getPointsGagnes(): int
    {
        if (!$this->isAReussi()) {
            return 0;
        }
        // Les points de la simulation sont calculés comme la différence : pointsReussite total - somme des points des quiz
        return $this->module?->getPointsSimulation() ?? 0;
    }
}