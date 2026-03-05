<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: "notification")]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 20)]
    private ?string $type = null; // INFO, SUCCESS, WARNING, DANGER

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $titre = null;

    #[ORM\Column(type: 'text')]
    private ?string $message = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $urlAction = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $estLu = false;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateLecture = null;

    // Relation
    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Utilisateur $utilisateur = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
    }

    // Getters et Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getUrlAction(): ?string
    {
        return $this->urlAction;
    }

    public function setUrlAction(?string $urlAction): static
    {
        $this->urlAction = $urlAction;
        return $this;
    }

    public function isEstLu(): bool
    {
        return $this->estLu;
    }

    public function setEstLu(bool $estLu): static
    {
        $this->estLu = $estLu;
        return $this;
    }

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeInterface $dateCreation): static
    {
        $this->dateCreation = $dateCreation;
        return $this;
    }

    public function getDateLecture(): ?\DateTimeInterface
    {
        return $this->dateLecture;
    }

    public function setDateLecture(?\DateTimeInterface $dateLecture): static
    {
        $this->dateLecture = $dateLecture;
        return $this;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): static
    {
        $this->utilisateur = $utilisateur;
        return $this;
    }

    // Méthodes utilitaires

    public function marquerCommeLu(): static
    {
        $this->estLu = true;
        $this->dateLecture = new \DateTime();
        return $this;
    }

    public function getIcone(): string
    {
        return match($this->type) {
            'INFO' => 'bi-info-circle',
            'SUCCESS' => 'bi-check-circle',
            'WARNING' => 'bi-exclamation-triangle',
            'DANGER' => 'bi-x-circle',
            default => 'bi-bell'
        };
    }

    public function getCouleur(): string
    {
        return match($this->type) {
            'INFO' => 'info',
            'SUCCESS' => 'success',
            'WARNING' => 'warning',
            'DANGER' => 'danger',
            default => 'secondary'
        };
    }
}