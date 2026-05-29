<?php

namespace App\Entity;

use App\Repository\ChapitreRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChapitreRepository::class)]
#[ORM\Table(name: 'chapitre')]
class Chapitre
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $titre = null;

    #[ORM\Column(type: 'text')]
    private ?string $contenu = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $urlVideo = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $dureeVideo = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateCreation = null;

    // Quiz intégré (remplace l'entité Quiz)
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $quizQuestions = null;

    #[ORM\Column(type: 'integer', nullable: true, options: ['default' => 3])]
    private ?int $quizNombreTentativesMax = 3;

    #[ORM\Column(type: 'integer', nullable: true, options: ['default' => 70])]
    private ?int $quizScoreMinimum = 70;

    #[ORM\ManyToOne(targetEntity: ModuleFormation::class, inversedBy: 'chapitres')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ModuleFormation $module = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
    }

    // ========================================
    // Getters / Setters
    // ========================================

    public function getId(): ?int { return $this->id; }

    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(string $titre): static { $this->titre = $titre; return $this; }

    public function getContenu(): ?string { return $this->contenu; }
    public function setContenu(string $contenu): static { $this->contenu = $contenu; return $this; }

    public function getUrlVideo(): ?string { return $this->urlVideo; }
    public function setUrlVideo(?string $urlVideo): static { $this->urlVideo = $urlVideo; return $this; }

    public function getDureeVideo(): ?int { return $this->dureeVideo; }
    public function setDureeVideo(?int $dureeVideo): static { $this->dureeVideo = $dureeVideo; return $this; }

    public function getDateCreation(): ?\DateTimeInterface { return $this->dateCreation; }
    public function setDateCreation(\DateTimeInterface $dateCreation): static { $this->dateCreation = $dateCreation; return $this; }

    // Quiz intégré
    public function getQuizQuestions(): ?array { return $this->quizQuestions; }
    public function setQuizQuestions(?array $quizQuestions): static { $this->quizQuestions = $quizQuestions; return $this; }

    public function getQuizNombreTentativesMax(): ?int { return $this->quizNombreTentativesMax; }
    public function setQuizNombreTentativesMax(?int $quizNombreTentativesMax): static { $this->quizNombreTentativesMax = $quizNombreTentativesMax; return $this; }

    public function getQuizScoreMinimum(): ?int { return $this->quizScoreMinimum; }
    public function setQuizScoreMinimum(?int $quizScoreMinimum): static { $this->quizScoreMinimum = $quizScoreMinimum; return $this; }

    public function getModule(): ?ModuleFormation { return $this->module; }
    public function setModule(?ModuleFormation $module): static { $this->module = $module; return $this; }

    // ========================================
    // Méthodes calculées (pas de stockage)
    // ========================================

    public function hasQuiz(): bool
    {
        return !empty($this->quizQuestions);
    }

    public function getTotalQuestions(): int
    {
        return count($this->quizQuestions ?? []);
    }

    /**
     * Calcule la somme des points de toutes les questions du quiz.
     */
    public function getTotalPoints(): int
    {
        $total = 0;
        if ($this->quizQuestions) {
            foreach ($this->quizQuestions as $question) {
                $total += $question['points'] ?? 0;
            }
        }
        return $total;
    }

    public function __toString(): string { return $this->titre ?? ''; }
}