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

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $emailOuvert = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $lienClique = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $donneesSubmises = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $emailSignale = false;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateEnvoi = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateOuverture = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateClic = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateSubmission = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateSignalement = null;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    private ?string $adresseIp = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $agentUtilisateur = null;

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

    #[ORM\OneToOne(targetEntity: DonneesSubmisesPhishing::class, mappedBy: 'resultatPhishing', cascade: ['persist', 'remove'])]
    private ?DonneesSubmisesPhishing $donneesSubmises_detail = null;

    public function getId(): ?int { return $this->id; }
    public function getJetonTrackingUnique(): ?string { return $this->jetonTrackingUnique; }
    public function setJetonTrackingUnique(string $jetonTrackingUnique): static { $this->jetonTrackingUnique = $jetonTrackingUnique; return $this; }
    public function isEmailEnvoye(): bool { return $this->emailEnvoye; }
    public function setEmailEnvoye(bool $emailEnvoye): static { $this->emailEnvoye = $emailEnvoye; return $this; }
    public function isEmailOuvert(): bool { return $this->emailOuvert; }
    public function setEmailOuvert(bool $emailOuvert): static { $this->emailOuvert = $emailOuvert; return $this; }
    public function isLienClique(): bool { return $this->lienClique; }
    public function setLienClique(bool $lienClique): static { $this->lienClique = $lienClique; return $this; }
    public function isDonneesSubmises(): bool { return $this->donneesSubmises; }
    public function setDonneesSubmises(bool $donneesSubmises): static { $this->donneesSubmises = $donneesSubmises; return $this; }
    public function isEmailSignale(): bool { return $this->emailSignale; }
    public function setEmailSignale(bool $emailSignale): static { $this->emailSignale = $emailSignale; return $this; }
    public function getDateEnvoi(): ?\DateTimeInterface { return $this->dateEnvoi; }
    public function setDateEnvoi(?\DateTimeInterface $dateEnvoi): static { $this->dateEnvoi = $dateEnvoi; return $this; }
    public function getDateOuverture(): ?\DateTimeInterface { return $this->dateOuverture; }
    public function setDateOuverture(?\DateTimeInterface $dateOuverture): static { $this->dateOuverture = $dateOuverture; return $this; }
    public function getDateClic(): ?\DateTimeInterface { return $this->dateClic; }
    public function setDateClic(?\DateTimeInterface $dateClic): static { $this->dateClic = $dateClic; return $this; }
    public function getDateSubmission(): ?\DateTimeInterface { return $this->dateSubmission; }
    public function setDateSubmission(?\DateTimeInterface $dateSubmission): static { $this->dateSubmission = $dateSubmission; return $this; }
    public function getDateSignalement(): ?\DateTimeInterface { return $this->dateSignalement; }
    public function setDateSignalement(?\DateTimeInterface $dateSignalement): static { $this->dateSignalement = $dateSignalement; return $this; }
    public function getAdresseIp(): ?string { return $this->adresseIp; }
    public function setAdresseIp(?string $adresseIp): static { $this->adresseIp = $adresseIp; return $this; }
    public function getAgentUtilisateur(): ?string { return $this->agentUtilisateur; }
    public function setAgentUtilisateur(?string $agentUtilisateur): static { $this->agentUtilisateur = $agentUtilisateur; return $this; }
    public function getScore(): int { return $this->score; }
    public function setScore(int $score): static { $this->score = $score; return $this; }
    public function getPointsGagnes(): int { return $this->pointsGagnes; }
    public function setPointsGagnes(int $pointsGagnes): static { $this->pointsGagnes = $pointsGagnes; return $this; }
    public function getCampagne(): ?CampagnePhishing { return $this->campagne; }
    public function setCampagne(?CampagnePhishing $campagne): static { $this->campagne = $campagne; return $this; }
    public function getEmploye(): ?Employe { return $this->employe; }
    public function setEmploye(?Employe $employe): static { $this->employe = $employe; return $this; }
    public function getDonneesSubmisesDetail(): ?DonneesSubmisesPhishing { return $this->donneesSubmises_detail; }
    public function setDonneesSubmisesDetail(?DonneesSubmisesPhishing $donneesSubmises_detail): static { if ($donneesSubmises_detail === null && $this->donneesSubmises_detail !== null) { $this->donneesSubmises_detail->setResultatPhishing(null); } if ($donneesSubmises_detail !== null && $donneesSubmises_detail->getResultatPhishing() !== $this) { $donneesSubmises_detail->setResultatPhishing($this); } $this->donneesSubmises_detail = $donneesSubmises_detail; return $this; }
}