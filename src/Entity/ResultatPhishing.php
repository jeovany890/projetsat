<?php

namespace App\Entity;

use App\Repository\ResultatPhishingRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Cible phishing : envoi technique + suivi comportemental (une ligne par employé/campagne).
 */
#[ORM\Entity(repositoryClass: ResultatPhishingRepository::class)]
#[ORM\Table(name: 'resultat_phishing')]
class ResultatPhishing
{
    public const STATUT_PLANIFIE = 'PLANIFIE';
    public const STATUT_ENVOYE   = 'ENVOYE';
    public const STATUT_ECHOUE   = 'ECHOUE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    private ?string $jetonTrackingUnique = null;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => self::STATUT_PLANIFIE])]
    private string $statut = self::STATUT_PLANIFIE;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $emailDestinataire = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $sujetUtilise = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $datePlanifiee = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateEnvoi = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $messageErreur = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $nombreTentatives = 0;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateCreation = null;

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

    #[ORM\ManyToOne(targetEntity: CampagnePhishing::class, inversedBy: 'resultats')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CampagnePhishing $campagne = null;

    #[ORM\ManyToOne(targetEntity: Employe::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Employe $employe = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getJetonTrackingUnique(): ?string { return $this->jetonTrackingUnique; }
    public function setJetonTrackingUnique(string $t): static { $this->jetonTrackingUnique = $t; return $this; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }

    public function getEmailDestinataire(): ?string { return $this->emailDestinataire; }
    public function setEmailDestinataire(string $email): static { $this->emailDestinataire = $email; return $this; }

    public function getSujetUtilise(): ?string { return $this->sujetUtilise; }
    public function setSujetUtilise(string $sujet): static { $this->sujetUtilise = $sujet; return $this; }

    public function getDatePlanifiee(): ?\DateTimeInterface { return $this->datePlanifiee; }
    public function setDatePlanifiee(\DateTimeInterface $d): static { $this->datePlanifiee = $d; return $this; }

    public function getDateEnvoi(): ?\DateTimeInterface { return $this->dateEnvoi; }
    public function setDateEnvoi(?\DateTimeInterface $d): static { $this->dateEnvoi = $d; return $this; }

    public function getMessageErreur(): ?string { return $this->messageErreur; }
    public function setMessageErreur(?string $msg): static { $this->messageErreur = $msg; return $this; }

    public function getNombreTentatives(): int { return $this->nombreTentatives; }
    public function incrementerTentatives(): static { $this->nombreTentatives++; return $this; }

    public function getDateCreation(): ?\DateTimeInterface { return $this->dateCreation; }

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
    public function setEmploye(?Employe $employe): static
    {
        $this->employe = $employe;
        if ($employe && !$this->emailDestinataire) {
            $this->emailDestinataire = $employe->getEmail();
        }
        return $this;
    }

    /** Alias Twig / requêtes legacy — remplace l’ancien booléen emailEnvoye. */
    public function isEmailEnvoye(): bool
    {
        return $this->statut === self::STATUT_ENVOYE;
    }

    public function marquerCommeEnvoye(): static
    {
        $this->statut    = self::STATUT_ENVOYE;
        $this->dateEnvoi = new \DateTime();
        return $this;
    }

    public function marquerCommeEchoue(string $erreur): static
    {
        $this->statut        = self::STATUT_ECHOUE;
        $this->messageErreur = $erreur;
        return $this;
    }

    public function estEnvoye(): bool { return $this->statut === self::STATUT_ENVOYE; }
    public function estEchoue(): bool { return $this->statut === self::STATUT_ECHOUE; }
    public function estPlanifie(): bool { return $this->statut === self::STATUT_PLANIFIE; }

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
