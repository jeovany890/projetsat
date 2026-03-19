<?php

namespace App\Entity;

use App\Repository\QuizRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuizRepository::class)]
#[ORM\Table(name: "quiz")]
class Quiz
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $titre = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'integer', options: ['default' => 70])]
    private int $scoreMinimum = 70;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $tempsLimite = null;

    #[ORM\Column(type: 'integer', options: ['default' => 3])]
    private int $nombreTentativesMax = 3;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $melangerQuestions = true;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateCreation = null;

    // ✅ Quiz lié à un Chapitre
    #[ORM\OneToOne(targetEntity: Chapitre::class, inversedBy: 'quiz')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Chapitre $chapitre = null;

    // Relation module gardée nullable pour compatibilité
    #[ORM\OneToOne(targetEntity: ModuleFormation::class, inversedBy: 'quiz')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?ModuleFormation $module = null;

    #[ORM\OneToMany(targetEntity: QuestionQuiz::class, mappedBy: 'quiz', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(["ordre" => "ASC"])]
    private Collection $questions;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->questions = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(string $titre): static { $this->titre = $titre; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getScoreMinimum(): int { return $this->scoreMinimum; }
    public function setScoreMinimum(int $scoreMinimum): static { $this->scoreMinimum = $scoreMinimum; return $this; }
    public function getTempsLimite(): ?int { return $this->tempsLimite; }
    public function setTempsLimite(?int $tempsLimite): static { $this->tempsLimite = $tempsLimite; return $this; }
    public function getNombreTentativesMax(): int { return $this->nombreTentativesMax; }
    public function setNombreTentativesMax(int $nombreTentativesMax): static { $this->nombreTentativesMax = $nombreTentativesMax; return $this; }
    public function isMelangerQuestions(): bool { return $this->melangerQuestions; }
    public function setMelangerQuestions(bool $melangerQuestions): static { $this->melangerQuestions = $melangerQuestions; return $this; }
    public function getDateCreation(): ?\DateTimeInterface { return $this->dateCreation; }
    public function setDateCreation(\DateTimeInterface $dateCreation): static { $this->dateCreation = $dateCreation; return $this; }
    public function getChapitre(): ?Chapitre { return $this->chapitre; }
    public function setChapitre(?Chapitre $chapitre): static { $this->chapitre = $chapitre; return $this; }
    public function getModule(): ?ModuleFormation { return $this->module; }
    public function setModule(?ModuleFormation $module): static { $this->module = $module; return $this; }
    public function getQuestions(): Collection { return $this->questions; }
    public function addQuestion(QuestionQuiz $question): static { if (!$this->questions->contains($question)) { $this->questions->add($question); $question->setQuiz($this); } return $this; }
    public function removeQuestion(QuestionQuiz $question): static { if ($this->questions->removeElement($question)) { if ($question->getQuiz() === $this) { $question->setQuiz(null); } } return $this; }
    public function __toString(): string { return $this->titre ?? ''; }
}