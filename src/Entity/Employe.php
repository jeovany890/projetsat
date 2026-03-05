<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Employe extends Utilisateur
{
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $poste = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $totalPoints = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $totalEtoiles = 0;

    #[ORM\Column(type: 'float', options: ['default' => 50.00])]
    private float $scoreVigilance = 50.00;
    
    #[ORM\ManyToOne(targetEntity: Departement::class, inversedBy: 'employes')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Departement $departement = null;

    // Getters et Setters


    public function getPoste(): ?string
    {
        return $this->poste;
    }

    public function setPoste(?string $poste): static
    {
        $this->poste = $poste;
        return $this;
    }

    public function getTotalPoints(): int
    {
        return $this->totalPoints;
    }

    public function setTotalPoints(int $totalPoints): static
    {
        $this->totalPoints = $totalPoints;
        return $this;
    }

    public function ajouterPoints(int $points): static
    {
        $this->totalPoints += $points;
        return $this;
    }

    public function retirerPoints(int $points): static
    {
        $this->totalPoints -= $points;
        if ($this->totalPoints < 0) {
            $this->totalPoints = 0;
        }
        return $this;
    }

    public function getTotalEtoiles(): int
    {
        return $this->totalEtoiles;
    }

    public function setTotalEtoiles(int $totalEtoiles): static
    {
        $this->totalEtoiles = $totalEtoiles;
        return $this;
    }

    public function ajouterEtoiles(int $etoiles): static
    {
        $this->totalEtoiles += $etoiles;
        return $this;
    }

    public function getScoreVigilance(): float
    {
        return $this->scoreVigilance;
    }

    public function setScoreVigilance(float $scoreVigilance): static
    {
        // Limiter entre 0 et 100
        $this->scoreVigilance = max(0, min(100, $scoreVigilance));
        return $this;
    }

    public function ajusterScoreVigilance(float $ajustement): static
    {
        $this->scoreVigilance += $ajustement;
        $this->scoreVigilance = max(0, min(100, $this->scoreVigilance));
        return $this;
    }

    public function getNiveauVigilance(): string
    {
        return match(true) {
            $this->scoreVigilance >= 80 => 'Expert',
            $this->scoreVigilance >= 60 => 'Vigilant',
            $this->scoreVigilance >= 40 => 'Prudent',
            $this->scoreVigilance >= 20 => 'Débutant',
            default => 'À risque'
        };
    }
    public function getDepartement(): ?Departement
{
    return $this->departement;
}

public function setDepartement(?Departement $departement): static
{
    $this->departement = $departement;
    return $this;
}
}