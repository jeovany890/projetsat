<?php

namespace App\Entity;

use App\Repository\TentativeQuizRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TentativeQuizRepository::class)]
#[ORM\Table(name: 'tentative_quiz')]
class TentativeQuiz
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    private ?int $numeroTentative = null;

    // Seul le nombre de bonnes réponses est stocké (le reste est calculé)
    #[ORM\Column(type: 'integer')]
    private ?int $reponsesCorrectes = null;

    // Historique complet des réponses (JSON)
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

    #[ORM\ManyToOne(targetEntity: Chapitre::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Chapitre $chapitre = null;

    #[ORM\ManyToOne(targetEntity: ProgressionModule::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ProgressionModule $progression = null;

    // ========================================
    // Getters / Setters
    // ========================================

    public function getId(): ?int { return $this->id; }

    public function getNumeroTentative(): ?int { return $this->numeroTentative; }
    public function setNumeroTentative(int $numeroTentative): static { $this->numeroTentative = $numeroTentative; return $this; }

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

    public function getChapitre(): ?Chapitre { return $this->chapitre; }
    public function setChapitre(?Chapitre $chapitre): static { $this->chapitre = $chapitre; return $this; }

    public function getProgression(): ?ProgressionModule { return $this->progression; }
    public function setProgression(?ProgressionModule $progression): static { $this->progression = $progression; return $this; }

    // ========================================
    // Méthodes calculées (pas de stockage)
    // ========================================

    public function getTotalQuestions(): int
    {
        return $this->chapitre?->getTotalQuestions() ?? 0;
    }

    public function getScore(): float
    {
        $total = $this->getTotalQuestions();
        if ($total === 0) {
            return 0.0;
        }
        return round(($this->reponsesCorrectes / $total) * 100, 2);
    }

    public function isAReussi(): bool
    {
        $seuil = $this->chapitre?->getQuizScoreMinimum() ?? 70;
        return $this->getScore() >= $seuil;
    }

    
}