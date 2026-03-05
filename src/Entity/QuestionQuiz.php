<?php

namespace App\Entity;

use App\Repository\QuestionQuizRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuestionQuizRepository::class)]
#[ORM\Table(name: "question_quiz")]
class QuestionQuiz
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    private ?string $question = null;

    #[ORM\Column(type: 'string', length: 20)]
    private ?string $typeQuestion = null;

    #[ORM\Column(type: 'json')]
    private array $options = [];

    #[ORM\Column(type: 'json')]
    private array $reponsesCorrectes = [];

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $explication = null;

    #[ORM\Column(type: 'integer', options: ['default' => 10])]
    private int $points = 10;

    #[ORM\Column(type: 'integer')]
    private ?int $ordre = null;

    #[ORM\ManyToOne(targetEntity: Quiz::class, inversedBy: 'questions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Quiz $quiz = null;

    public function getId(): ?int { return $this->id; }
    public function getQuestion(): ?string { return $this->question; }
    public function setQuestion(string $question): static { $this->question = $question; return $this; }
    public function getTypeQuestion(): ?string { return $this->typeQuestion; }
    public function setTypeQuestion(string $typeQuestion): static { $this->typeQuestion = $typeQuestion; return $this; }
    public function getOptions(): array { return $this->options; }
    public function setOptions(array $options): static { $this->options = $options; return $this; }
    public function getReponsesCorrectes(): array { return $this->reponsesCorrectes; }
    public function setReponsesCorrectes(array $reponsesCorrectes): static { $this->reponsesCorrectes = $reponsesCorrectes; return $this; }
    public function getExplication(): ?string { return $this->explication; }
    public function setExplication(?string $explication): static { $this->explication = $explication; return $this; }
    public function getPoints(): int { return $this->points; }
    public function setPoints(int $points): static { $this->points = $points; return $this; }
    public function getOrdre(): ?int { return $this->ordre; }
    public function setOrdre(int $ordre): static { $this->ordre = $ordre; return $this; }
    public function getQuiz(): ?Quiz { return $this->quiz; }
    public function setQuiz(?Quiz $quiz): static { $this->quiz = $quiz; return $this; }
}