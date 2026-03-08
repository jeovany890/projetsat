<?php

namespace App\Entity;

use App\Repository\EnvoiPhishingRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * EnvoiPhishing — représente l'envoi d'un email phishing
 * à un employé spécifique dans le cadre d'une campagne réaliste.
 *
 * Différence avec ResultatPhishing :
 *   - EnvoiPhishing = l'acte d'envoi (planifié/envoyé/échoué)
 *   - ResultatPhishing = le comportement de l'employé après réception
 */
#[ORM\Entity(repositoryClass: EnvoiPhishingRepository::class)]
#[ORM\Table(name: "envoi_phishing")]
class EnvoiPhishing
{
    // ========================================
    // STATUTS POSSIBLES
    // ========================================
    const STATUT_PLANIFIE  = 'PLANIFIE';
    const STATUT_ENVOYE    = 'ENVOYE';
    const STATUT_ECHOUE    = 'ECHOUE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    // Statut de l'envoi : PLANIFIE → ENVOYE ou ECHOUE
    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'PLANIFIE'])]
    private string $statut = self::STATUT_PLANIFIE;

    // Email de destination au moment de l'envoi (snapshot)
    #[ORM\Column(type: 'string', length: 255)]
    private ?string $emailDestinataire = null;

    // Sujet utilisé (peut être personnalisé depuis le gabarit)
    #[ORM\Column(type: 'string', length: 255)]
    private ?string $sujetUtilise = null;

    // Dates
    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $datePlanifiee = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateEnvoi = null;

    // Message d'erreur en cas d'échec SMTP
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $messageErreur = null;

    // Nombre de tentatives d'envoi (pour retry)
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $nombreTentatives = 0;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateCreation = null;

    // ========================================
    // RELATIONS
    // ========================================

    // ✅ Lien vers la campagne phishing (obligatoire)
    #[ORM\ManyToOne(targetEntity: CampagnePhishing::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CampagnePhishing $campagne = null;

    // ✅ Lien vers l'employé ciblé (obligatoire)
    #[ORM\ManyToOne(targetEntity: Employe::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Employe $employe = null;

    // ✅ Lien vers le résultat (créé quand l'employé interagit)
    #[ORM\OneToOne(targetEntity: ResultatPhishing::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ResultatPhishing $resultat = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
    }

    // ========================================
    // GETTERS & SETTERS
    // ========================================

    public function getId(): ?int { return $this->id; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }

    public function getEmailDestinataire(): ?string { return $this->emailDestinataire; }
    public function setEmailDestinataire(string $emailDestinataire): static
    {
        $this->emailDestinataire = $emailDestinataire;
        return $this;
    }

    public function getSujetUtilise(): ?string { return $this->sujetUtilise; }
    public function setSujetUtilise(string $sujetUtilise): static
    {
        $this->sujetUtilise = $sujetUtilise;
        return $this;
    }

    public function getDatePlanifiee(): ?\DateTimeInterface { return $this->datePlanifiee; }
    public function setDatePlanifiee(\DateTimeInterface $datePlanifiee): static
    {
        $this->datePlanifiee = $datePlanifiee;
        return $this;
    }

    public function getDateEnvoi(): ?\DateTimeInterface { return $this->dateEnvoi; }
    public function setDateEnvoi(?\DateTimeInterface $dateEnvoi): static
    {
        $this->dateEnvoi = $dateEnvoi;
        return $this;
    }

    public function getMessageErreur(): ?string { return $this->messageErreur; }
    public function setMessageErreur(?string $messageErreur): static
    {
        $this->messageErreur = $messageErreur;
        return $this;
    }

    public function getNombreTentatives(): int { return $this->nombreTentatives; }
    public function incrementerTentatives(): static
    {
        $this->nombreTentatives++;
        return $this;
    }

    public function getDateCreation(): ?\DateTimeInterface { return $this->dateCreation; }

    // Relations
    public function getCampagne(): ?CampagnePhishing { return $this->campagne; }
    public function setCampagne(?CampagnePhishing $campagne): static
    {
        $this->campagne = $campagne;
        return $this;
    }

    public function getEmploye(): ?Employe { return $this->employe; }
    public function setEmploye(?Employe $employe): static
    {
        $this->employe = $employe;
        // Snapshot de l'email au moment de la création
        if ($employe && !$this->emailDestinataire) {
            $this->emailDestinataire = $employe->getEmail();
        }
        return $this;
    }

    public function getResultat(): ?ResultatPhishing { return $this->resultat; }
    public function setResultat(?ResultatPhishing $resultat): static
    {
        $this->resultat = $resultat;
        return $this;
    }

    // ========================================
    // MÉTHODES MÉTIER
    // ========================================

    public function marquerCommeEnvoye(): static
    {
        $this->statut = self::STATUT_ENVOYE;
        $this->dateEnvoi = new \DateTime();
        return $this;
    }

    public function marquerCommeEchoue(string $erreur): static
    {
        $this->statut = self::STATUT_ECHOUE;
        $this->messageErreur = $erreur;
        return $this;
    }

    public function estEnvoye(): bool { return $this->statut === self::STATUT_ENVOYE; }
    public function estEchoue(): bool { return $this->statut === self::STATUT_ECHOUE; }
    public function aUnResultat(): bool { return $this->resultat !== null; }
}