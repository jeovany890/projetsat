<?php

namespace App\Entity;

use App\Repository\TentativeQuizRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TentativeQuizRepository::class)]
#[ORM\Table(name: "tentative_quiz")]
class TentativeQuiz
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    private ?int $numeroTentative = null;

    #[ORM\Column(type: 'integer')]
    private ?int $score = null;

    #[ORM\Column(type: 'integer')]
    private ?int $totalQuestions = null;

    #[ORM\Column(type: 'integer')]
    private ?int $reponsesCorrectes = null;

    #[ORM\Column(type: 'json')]
    private array $reponses = [];

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $aReussi = false;

    #[ORM\Column(type: 'integer')]
    private ?int $tempsPasseSecondes = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateTermine = null;

    #[ORM\ManyToOne(targetEntity: Employe::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Employe $employe = null;

    #[ORM\ManyToOne(targetEntity: Quiz::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Quiz $quiz = null;

    public function getId(): ?int { return $this->id; }
    public function getNumeroTentative(): ?int { return $this->numeroTentative; }
    public function setNumeroTentative(int $numeroTentative): static { $this->numeroTentative = $numeroTentative; return $this; }
    public function getScore(): ?int { return $this->score; }
    public function setScore(int $score): static { $this->score = $score; return $this; }
    public function getTotalQuestions(): ?int { return $this->totalQuestions; }
    public function setTotalQuestions(int $totalQuestions): static { $this->totalQuestions = $totalQuestions; return $this; }
    public function getReponsesCorrectes(): ?int { return $this->reponsesCorrectes; }
    public function setReponsesCorrectes(int $reponsesCorrectes): static { $this->reponsesCorrectes = $reponsesCorrectes; return $this; }
    public function getReponses(): array { return $this->reponses; }
    public function setReponses(array $reponses): static { $this->reponses = $reponses; return $this; }
    public function isAReussi(): bool { return $this->aReussi; }
    public function setAReussi(bool $aReussi): static { $this->aReussi = $aReussi; return $this; }
    public function getTempsPasseSecondes(): ?int { return $this->tempsPasseSecondes; }
    public function setTempsPasseSecondes(int $tempsPasseSecondes): static { $this->tempsPasseSecondes = $tempsPasseSecondes; return $this; }
    public function getDateDebut(): ?\DateTimeInterface { return $this->dateDebut; }
    public function setDateDebut(\DateTimeInterface $dateDebut): static { $this->dateDebut = $dateDebut; return $this; }
    public function getDateTermine(): ?\DateTimeInterface { return $this->dateTermine; }
    public function setDateTermine(\DateTimeInterface $dateTermine): static { $this->dateTermine = $dateTermine; return $this; }
    public function getEmploye(): ?Employe { return $this->employe; }
    public function setEmploye(?Employe $employe): static { $this->employe = $employe; return $this; }
    public function getQuiz(): ?Quiz { return $this->quiz; }
    public function setQuiz(?Quiz $quiz): static { $this->quiz = $quiz; return $this; }
}