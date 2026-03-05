<?php

namespace App\Entity;

use App\Repository\CategorieRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CategorieRepository::class)]
#[ORM\Table(name: "categorie")]
class Categorie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    private ?string $titre = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'ACTIF'])]
    private string $statut = 'ACTIF';

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\OneToMany(targetEntity: ModuleFormation::class, mappedBy: 'categorie')]
    private Collection $modules;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->modules = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(string $titre): static { $this->titre = $titre; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }
    public function getDateCreation(): ?\DateTimeInterface { return $this->dateCreation; }
    public function setDateCreation(\DateTimeInterface $dateCreation): static { $this->dateCreation = $dateCreation; return $this; }
    public function getModules(): Collection { return $this->modules; }
    public function addModule(ModuleFormation $module): static { if (!$this->modules->contains($module)) { $this->modules->add($module); $module->setCategorie($this); } return $this; }
    public function removeModule(ModuleFormation $module): static { if ($this->modules->removeElement($module)) { if ($module->getCategorie() === $this) { $module->setCategorie(null); } } return $this; }
    public function __toString(): string { return $this->titre ?? ''; }
}