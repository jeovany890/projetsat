<?php

namespace App\Entity;

use App\Repository\RSSIRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RSSIRepository::class)]
class RSSI extends Utilisateur
{
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $jetonActivation = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $jetonExpiration = null;

    // ✅ AJOUT : Lien direct vers l'entreprise du RSSI
    #[ORM\ManyToOne(targetEntity: Entreprise::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Entreprise $entreprise = null;

    // ========================================
    // JETON ACTIVATION
    // ========================================

    public function getJetonActivation(): ?string
    {
        return $this->jetonActivation;
    }

    public function setJetonActivation(?string $jetonActivation): static
    {
        $this->jetonActivation = $jetonActivation;
        return $this;
    }

    public function getJetonExpiration(): ?\DateTimeInterface
    {
        return $this->jetonExpiration;
    }

    public function setJetonExpiration(?\DateTimeInterface $jetonExpiration): static
    {
        $this->jetonExpiration = $jetonExpiration;
        return $this;
    }

    public function isJetonActivationValide(): bool
    {
        if (!$this->jetonActivation || !$this->jetonExpiration) {
            return false;
        }
        return $this->jetonExpiration > new \DateTime();
    }

    public function genererJetonActivation(): void
    {
        $this->jetonActivation = bin2hex(random_bytes(32));
        $this->jetonExpiration = (new \DateTime())->modify('+48 hours');
    }

    // ========================================
    // RELATION ENTREPRISE
    // ========================================

    public function getEntreprise(): ?Entreprise
    {
        return $this->entreprise;
    }

    public function setEntreprise(?Entreprise $entreprise): static
    {
        $this->entreprise = $entreprise;
        return $this;
    }
}