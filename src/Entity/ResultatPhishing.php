<?php

namespace App\Entity;

use App\Repository\ResultatPhishingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ResultatPhishingRepository::class)]
#[ORM\Table(name: "resultat_phishing")]
class ResultatPhishing
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    private ?string $jetonTrackingUnique = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $emailEnvoye = false;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateEnvoi = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $lienClique = false;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateClic = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $signale = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $soumissionDonnees = false;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $score = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $pointsGagnes = 0;

    // Relations
    #[ORM\ManyToOne(targetEntity: CampagnePhishing::class, inversedBy: 'resultats')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CampagnePhishing $campagne = null;

    #[ORM\ManyToOne(targetEntity: Employe::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Employe $employe = null;

    #[ORM\OneToOne(targetEntity: EnvoiPhishing::class, inversedBy: 'resultat')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?EnvoiPhishing $envoi = null;

    // ─────────────────────────────────────────────────────────────
    // Getters & Setters
    // ─────────────────────────────────────────────────────────────
    public function getId(): ?int { return $this->id; }

    public function getJetonTrackingUnique(): ?string { return $this->jetonTrackingUnique; }
    public function setJetonTrackingUnique(string $t): static { $this->jetonTrackingUnique = $t; return $this; }

    public function isEmailEnvoye(): bool { return $this->emailEnvoye; }
    public function setEmailEnvoye(bool $v): static { $this->emailEnvoye = $v; return $this; }

    public function getDateEnvoi(): ?\DateTimeInterface { return $this->dateEnvoi; }
    public function setDateEnvoi(?\DateTimeInterface $d): static { $this->dateEnvoi = $d; return $this; }

    public function isLienClique(): bool { return $this->lienClique; }
    public function setLienClique(bool $v): static { $this->lienClique = $v; return $this; }

    public function getDateClic(): ?\DateTimeInterface { return $this->dateClic; }
    public function setDateClic(?\DateTimeInterface $d): static { $this->dateClic = $d; return $this; }

    public function isSignale(): bool { return $this->signale; }
    public function setSignale(bool $v): static { $this->signale = $v; return $this; }

    public function isSoumissionDonnees(): bool { return $this->soumissionDonnees; }
    public function setSoumissionDonnees(bool $v): static { $this->soumissionDonnees = $v; return $this; }

    public function getScore(): int { return $this->score; }
    public function setScore(int $s): static { $this->score = $s; return $this; }

    public function getPointsGagnes(): int { return $this->pointsGagnes; }
    public function setPointsGagnes(int $p): static { $this->pointsGagnes = $p; return $this; }

    public function getCampagne(): ?CampagnePhishing { return $this->campagne; }
    public function setCampagne(?CampagnePhishing $c): static { $this->campagne = $c; return $this; }

    public function getEmploye(): ?Employe { return $this->employe; }
    public function setEmploye(?Employe $e): static { $this->employe = $e; return $this; }

    public function getEnvoi(): ?EnvoiPhishing { return $this->envoi; }
    public function setEnvoi(?EnvoiPhishing $envoi): static { $this->envoi = $envoi; return $this; }

    // ─────────────────────────────────────────────────────────────
    // Méthodes métier (simplifiées, sans comportement)
    // ─────────────────────────────────────────────────────────────
    public function marquerCommeSignale(): void
    {
        $this->signale = true;
    }

    public function marquerSoumissionDonnees(): void
    {
        $this->soumissionDonnees = true;
        if (!$this->lienClique) {
            $this->lienClique = true;
            $this->dateClic = new \DateTime();
        }
    }
}