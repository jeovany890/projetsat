<?php

namespace App\Entity;

use App\Repository\QuizRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuizRepository::class)]
#[ORM\Table(name: "quiz")]
class Quiz
{
    const TYPE_CHAPITRE = 'CHAPITRE';
    const TYPE_MODULE   = 'MODULE';
    const TYPE_INITIAL  = 'INITIAL';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'CHAPITRE'])]
    private string $typeQuiz = self::TYPE_CHAPITRE;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $titre = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'integer', options: ['default' => 70])]
    private int $scoreMinimum = 70;

    #[ORM\Column(type: 'integer', options: ['default' => 3])]
    private int $nombreTentativesMax = 3;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateCreation = null;

    // Quiz lié à un chapitre (chapitre_id existe en BD)
    #[ORM\OneToOne(targetEntity: Chapitre::class, inversedBy: 'quiz')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Chapitre $chapitre = null;

    // plus de module_id en BD — relation supprimée
    private ?ModuleFormation $module = null;

    // NOUVEAU : stockage des questions au format JSON
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $questions = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getTypeQuiz(): string { return $this->typeQuiz; }
    public function setTypeQuiz(string $typeQuiz): static { $this->typeQuiz = $typeQuiz; return $this; }
    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(string $titre): static { $this->titre = $titre; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getScoreMinimum(): int { return $this->scoreMinimum; }
    public function setScoreMinimum(int $scoreMinimum): static { $this->scoreMinimum = $scoreMinimum; return $this; }
    public function getNombreTentativesMax(): int { return $this->nombreTentativesMax; }
    public function setNombreTentativesMax(int $n): static { $this->nombreTentativesMax = $n; return $this; }
    public function getDateCreation(): ?\DateTimeInterface { return $this->dateCreation; }
    public function setDateCreation(\DateTimeInterface $d): static { $this->dateCreation = $d; return $this; }
    public function getChapitre(): ?Chapitre { return $this->chapitre; }
    public function setChapitre(?Chapitre $chapitre): static { $this->chapitre = $chapitre; return $this; }
    public function getModule(): ?ModuleFormation { return $this->module; }
    public function setModule(?ModuleFormation $module): static { $this->module = $module; return $this; }

    // Nouveaux accesseurs pour les questions
    public function getQuestions(): ?array { return $this->questions; }
    public function setQuestions(?array $questions): static { $this->questions = $questions; return $this; }

    // Méthodes utilitaires
    public function estInitial(): bool    { return $this->typeQuiz === self::TYPE_INITIAL; }
    public function estDeChapitre(): bool  { return $this->typeQuiz === self::TYPE_CHAPITRE; }
    public function estDeModule(): bool    { return $this->typeQuiz === self::TYPE_MODULE; }

    public function getNombreQuestions(): int 
    { 
        return $this->questions ? count($this->questions) : 0; 
    }

    public function getPointsTotal(): int
    {
        $points = 0;
        foreach ($this->questions ?? [] as $question) {
            $points += isset($question['points']) ? (int) $question['points'] : 0;
        }
        return $points;
    }

    public function __toString(): string { return $this->titre ?? ''; }
}