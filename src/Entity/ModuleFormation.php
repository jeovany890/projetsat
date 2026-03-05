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

    #[ORM\Column(type: 'integer', options: ['default' => 2])]
    private int $etoilesReussite = 2;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $ordreAffichage = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $estPublie = false;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\ManyToOne(targetEntity: Categorie::class, inversedBy: 'modules')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Categorie $categorie = null;

    #[ORM\OneToMany(targetEntity: Chapitre::class, mappedBy: 'module', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(["ordre" => "ASC"])]
    private Collection $chapitres;

    #[ORM\OneToOne(targetEntity: Quiz::class, mappedBy: 'module', cascade: ['persist', 'remove'])]
    private ?Quiz $quiz = null;

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
    public function getPointsReussite(): int { return $this->pointsReussite; }
    public function setPointsReussite(int $pointsReussite): static { $this->pointsReussite = $pointsReussite; return $this; }
    public function getEtoilesReussite(): int { return $this->etoilesReussite; }
    public function setEtoilesReussite(int $etoilesReussite): static { $this->etoilesReussite = $etoilesReussite; return $this; }
    public function getOrdreAffichage(): int { return $this->ordreAffichage; }
    public function setOrdreAffichage(int $ordreAffichage): static { $this->ordreAffichage = $ordreAffichage; return $this; }
    public function isEstPublie(): bool { return $this->estPublie; }
    public function setEstPublie(bool $estPublie): static { $this->estPublie = $estPublie; return $this; }
    public function getDateCreation(): ?\DateTimeInterface { return $this->dateCreation; }
    public function setDateCreation(\DateTimeInterface $dateCreation): static { $this->dateCreation = $dateCreation; return $this; }
    public function getCategorie(): ?Categorie { return $this->categorie; }
    public function setCategorie(?Categorie $categorie): static { $this->categorie = $categorie; return $this; }
    public function getChapitres(): Collection { return $this->chapitres; }
    public function addChapitre(Chapitre $chapitre): static { if (!$this->chapitres->contains($chapitre)) { $this->chapitres->add($chapitre); $chapitre->setModule($this); } return $this; }
    public function removeChapitre(Chapitre $chapitre): static { if ($this->chapitres->removeElement($chapitre)) { if ($chapitre->getModule() === $this) { $chapitre->setModule(null); } } return $this; }
    public function getQuiz(): ?Quiz { return $this->quiz; }
    public function setQuiz(?Quiz $quiz): static { if ($quiz === null && $this->quiz !== null) { $this->quiz->setModule(null); } if ($quiz !== null && $quiz->getModule() !== $this) { $quiz->setModule($this); } $this->quiz = $quiz; return $this; }
    public function __toString(): string { return $this->titre ?? ''; }
}