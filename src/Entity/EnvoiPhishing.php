<?php

namespace App\Entity;

use App\Repository\EnvoiPhishingRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * EnvoiPhishing — représente l'acte d'envoi d'un email phishing.
 * C'est la table racine : elle est créée en premier, le ResultatPhishing
 * en est la conséquence et porte la FK envoi_id (OneToOne propriétaire).
 */
#[ORM\Entity(repositoryClass: EnvoiPhishingRepository::class)]
#[ORM\Table(name: "envoi_phishing")]
class EnvoiPhishing
{
    const STATUT_PLANIFIE = 'PLANIFIE';
    const STATUT_ENVOYE   = 'ENVOYE';
    const STATUT_ECHOUE   = 'ECHOUE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'PLANIFIE'])]
    private string $statut = self::STATUT_PLANIFIE;

    // Snapshot email au moment de l'envoi
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

    // ── Relations ──

    #[ORM\ManyToOne(targetEntity: CampagnePhishing::class, inversedBy: 'envois')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CampagnePhishing $campagne = null;

    #[ORM\ManyToOne(targetEntity: Employe::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Employe $employe = null;

    /**
     * Relation inverse OneToOne vers ResultatPhishing.
     * C'est ResultatPhishing qui porte la FK (envoi_id) — il est le propriétaire.
     * On accède au résultat via $envoi->getResultat() grâce au mappedBy.
     */
    #[ORM\OneToOne(targetEntity: ResultatPhishing::class, mappedBy: 'envoi')]
    private ?ResultatPhishing $resultat = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

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

    public function getCampagne(): ?CampagnePhishing { return $this->campagne; }
    public function setCampagne(?CampagnePhishing $campagne): static { $this->campagne = $campagne; return $this; }

    public function getEmploye(): ?Employe { return $this->employe; }
    public function setEmploye(?Employe $employe): static
    {
        $this->employe = $employe;
        if ($employe && !$this->emailDestinataire) {
            $this->emailDestinataire = $employe->getEmail();
        }
        return $this;
    }

    // Relation inverse — ResultatPhishing est le propriétaire de la FK
    public function getResultat(): ?ResultatPhishing { return $this->resultat; }

    // ── Méthodes métier ──
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
    public function aUnResultat(): bool { return $this->resultat !== null; }
}