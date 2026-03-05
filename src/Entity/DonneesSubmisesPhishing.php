<?php

namespace App\Entity;

use App\Repository\DonneesSubmisesPhishingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DonneesSubmisesPhishingRepository::class)]
#[ORM\Table(name: "donnees_submises_phishing")]
class DonneesSubmisesPhishing
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'json')]
    private array $champsSubmis = [];

    #[ORM\Column(type: 'integer')]
    private ?int $nombreChamps = null;

    #[ORM\Column(type: 'string', length: 20)]
    private ?string $gravite = null;

    #[ORM\Column(type: 'string', length: 45)]
    private ?string $adresseIp = null;

    #[ORM\Column(type: 'text')]
    private ?string $agentUtilisateur = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateSubmission = null;

    #[ORM\OneToOne(targetEntity: ResultatPhishing::class, inversedBy: 'donneesSubmises_detail')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ResultatPhishing $resultatPhishing = null;

    public function __construct()
    {
        $this->dateSubmission = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getChampsSubmis(): array { return $this->champsSubmis; }
    public function setChampsSubmis(array $champsSubmis): static { $this->champsSubmis = $champsSubmis; return $this; }
    public function getNombreChamps(): ?int { return $this->nombreChamps; }
    public function setNombreChamps(int $nombreChamps): static { $this->nombreChamps = $nombreChamps; return $this; }
    public function getGravite(): ?string { return $this->gravite; }
    public function setGravite(string $gravite): static { $this->gravite = $gravite; return $this; }
    public function getAdresseIp(): ?string { return $this->adresseIp; }
    public function setAdresseIp(string $adresseIp): static { $this->adresseIp = $adresseIp; return $this; }
    public function getAgentUtilisateur(): ?string { return $this->agentUtilisateur; }
    public function setAgentUtilisateur(string $agentUtilisateur): static { $this->agentUtilisateur = $agentUtilisateur; return $this; }
    public function getDateSubmission(): ?\DateTimeInterface { return $this->dateSubmission; }
    public function setDateSubmission(\DateTimeInterface $dateSubmission): static { $this->dateSubmission = $dateSubmission; return $this; }
    public function getResultatPhishing(): ?ResultatPhishing { return $this->resultatPhishing; }
    public function setResultatPhishing(?ResultatPhishing $resultatPhishing): static { $this->resultatPhishing = $resultatPhishing; return $this; }
}