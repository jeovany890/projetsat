<?php

namespace App\Entity;

use App\Repository\ProgressionModuleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProgressionModuleRepository::class)]
#[ORM\Table(name: "progression_module")]
class ProgressionModule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'NON_COMMENCE'])]
    private string $statut = 'NON_COMMENCE';

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $pourcentageProgression = 0;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateTermine = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateDernierAcces = null;

    #[ORM\Column(type: 'string', length: 30, options: ['default' => 'CAMPAGNE'])]
    private string $typeAttribution = 'CAMPAGNE';

    #[ORM\ManyToOne(targetEntity: Employe::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Employe $employe = null;

    #[ORM\ManyToOne(targetEntity: ModuleFormation::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ModuleFormation $module = null;

    #[ORM\ManyToOne(targetEntity: CampagneFormation::class, inversedBy: 'progressions')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?CampagneFormation $campagne = null;

    // ── Getters / Setters ────────────────────────────────────────────────

    public function getId(): ?int { return $this->id; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }

    public function getPourcentageProgression(): int { return $this->pourcentageProgression; }
    public function setPourcentageProgression(int $v): static { $this->pourcentageProgression = $v; return $this; }

    public function getDateDebut(): ?\DateTimeInterface { return $this->dateDebut; }
    public function setDateDebut(?\DateTimeInterface $d): static { $this->dateDebut = $d; return $this; }

    public function getDateTermine(): ?\DateTimeInterface { return $this->dateTermine; }
    public function setDateTermine(?\DateTimeInterface $d): static { $this->dateTermine = $d; return $this; }

    public function getDateDernierAcces(): ?\DateTimeInterface { return $this->dateDernierAcces; }
    public function setDateDernierAcces(?\DateTimeInterface $d): static { $this->dateDernierAcces = $d; return $this; }

    public function getTypeAttribution(): string { return $this->typeAttribution; }
    public function setTypeAttribution(string $t): static { $this->typeAttribution = $t; return $this; }

    public function getEmploye(): ?Employe { return $this->employe; }
    public function setEmploye(?Employe $e): static { $this->employe = $e; return $this; }

    public function getModule(): ?ModuleFormation { return $this->module; }
    public function setModule(?ModuleFormation $m): static { $this->module = $m; return $this; }

    public function getCampagne(): ?CampagneFormation { return $this->campagne; }
    public function setCampagne(?CampagneFormation $c): static { $this->campagne = $c; return $this; }

    // ── Calculés dynamiquement — aucune colonne BD ───────────────────────

    /**
     * Retard calculé depuis la date de fin de la campagne.
     * Les progressions automatiques hors campagne n'ont pas d'échéance individuelle.
     */
    public function isEstEnRetard(): bool
    {
        if ($this->statut === 'TERMINE') return false;
        $campagne = $this->getCampagne();
        if (!$campagne || !$campagne->getDateFin()) {
            return false;
        }
        return new \DateTime() > $campagne->getDateFin();
    }

    /**
     * Durée estimée lue depuis le module.
     * L'ancienne colonne temps_passe_minutes était toujours 0 → supprimée.
     */
    public function getDureeEstimee(): int
    {
        return $this->module?->getDureeEstimee() ?? 0;
    }

    /**
     * Points gagnés = points du module si TERMINE, sinon 0.
     * L'ancienne colonne points_gagnes n'était jamais mise à jour → supprimée.
     */
    public function getPointsGagnes(): int
    {
        if ($this->statut !== 'TERMINE') return 0;
        return $this->module?->getPointsReussite() ?? 0;
    }

    /**
     * Étoiles gagnées = étoiles du module si TERMINE, sinon 0.
     * L'ancienne colonne etoiles_gagnees n'était jamais mise à jour → supprimée.
     */
   
}
