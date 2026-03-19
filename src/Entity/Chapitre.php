<?php

namespace App\Entity;

use App\Repository\ChapitreRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChapitreRepository::class)]
#[ORM\Table(name: "chapitre")]
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

    #[ORM\Column(type: 'integer')]
    private ?int $ordre = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\ManyToOne(targetEntity: ModuleFormation::class, inversedBy: 'chapitres')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ModuleFormation $module = null;

    // ✅ NOUVEAU : Quiz lié à ce chapitre
    #[ORM\OneToOne(targetEntity: Quiz::class, mappedBy: 'chapitre', cascade: ['persist', 'remove'])]
    private ?Quiz $quiz = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(string $titre): static { $this->titre = $titre; return $this; }
    public function getContenu(): ?string { return $this->contenu; }
    public function setContenu(string $contenu): static { $this->contenu = $contenu; return $this; }
    public function getUrlVideo(): ?string { return $this->urlVideo; }
    public function setUrlVideo(?string $urlVideo): static { $this->urlVideo = $urlVideo; return $this; }
    public function getDureeVideo(): ?int { return $this->dureeVideo; }
    public function setDureeVideo(?int $dureeVideo): static { $this->dureeVideo = $dureeVideo; return $this; }
    public function getOrdre(): ?int { return $this->ordre; }
    public function setOrdre(int $ordre): static { $this->ordre = $ordre; return $this; }
    public function getDateCreation(): ?\DateTimeInterface { return $this->dateCreation; }
    public function setDateCreation(\DateTimeInterface $dateCreation): static { $this->dateCreation = $dateCreation; return $this; }
    public function getModule(): ?ModuleFormation { return $this->module; }
    public function setModule(?ModuleFormation $module): static { $this->module = $module; return $this; }
    public function getQuiz(): ?Quiz { return $this->quiz; }
    public function setQuiz(?Quiz $quiz): static { $this->quiz = $quiz; return $this; }
    public function aVideo(): bool { return $this->urlVideo !== null; }
    public function aQuiz(): bool { return $this->quiz !== null; }
    public function __toString(): string { return $this->titre ?? ''; }
}