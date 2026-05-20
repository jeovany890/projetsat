<?php

namespace App\Entity;

use App\Repository\EmployeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmployeRepository::class)]
class Employe extends Utilisateur
{
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $poste = null;

    // Points gamification — cumulatifs, jamais négatifs
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $totalPoints = 0;

    // Score de vigilance — 0 à 100, initialisé à 50 à la création
    // Augmente/diminue selon les événements phishing et formations
    #[ORM\Column(type: 'float', options: ['default' => 50.0])]
    private float $scoreVigilance = 50.0;

    #[ORM\ManyToOne(targetEntity: Departement::class, inversedBy: 'employes')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Departement $departement = null;

    // ========================================
    // POSTE
    // ========================================

    public function getPoste(): ?string { return $this->poste; }
    public function setPoste(?string $poste): static { $this->poste = $poste; return $this; }

    // ========================================
    // POINTS (gamification — jamais négatifs)
    // ========================================

    public function getTotalPoints(): int { return $this->totalPoints; }

    public function setTotalPoints(int $totalPoints): static
    {
        $this->totalPoints = max(0, $totalPoints);
        return $this;
    }

    public function ajouterPoints(int $points): static
    {
        if ($points > 0) {
            $this->totalPoints += $points;
        }
        return $this;
    }

    // ========================================
    // SCORE VIGILANCE (0–100, stocké en BD)
    // ========================================

    public function getScoreVigilance(): float { return $this->scoreVigilance; }

    public function setScoreVigilance(float $score): static
    {
        $this->scoreVigilance = max(0.0, min(100.0, $score));
        return $this;
    }

    public function ajusterScoreVigilance(float $delta): static
    {
        $this->scoreVigilance = max(0.0, min(100.0, $this->scoreVigilance + $delta));
        return $this;
    }

    // ========================================
    // ÉTOILES — calculées dynamiquement depuis
    // le score de vigilance, JAMAIS stockées.
    //  0–19  → 1 ★
    // 20–39  → 2 ★
    // 40–59  → 3 ★
    // 60–79  → 4 ★
    // 80–100 → 5 ★
    // ========================================

    public function getNombreEtoiles(): int
    {
        return match(true) {
            $this->scoreVigilance >= 80 => 5,
            $this->scoreVigilance >= 60 => 4,
            $this->scoreVigilance >= 40 => 3,
            $this->scoreVigilance >= 20 => 2,
            default                     => 1,
        };
    }

    // ========================================
    // NIVEAU DE VIGILANCE (label texte)
    // ========================================

    public function getNiveauVigilance(): string
    {
        return match(true) {
            $this->scoreVigilance >= 80 => 'Expert',
            $this->scoreVigilance >= 60 => 'Vigilant',
            $this->scoreVigilance >= 40 => 'Prudent',
            $this->scoreVigilance >= 20 => 'Débutant',
            default                     => 'À risque',
        };
    }

    // ========================================
    // PROFIL DE RISQUE (pour le dashboard RSSI)
    // Calculé dynamiquement depuis le score.
    // ========================================

    public function getProfilRisque(): string
    {
        return match(true) {
            $this->scoreVigilance >= 60 => 'faible',
            $this->scoreVigilance >= 35 => 'moyen',
            default                     => 'élevé',
        };
    }

    // ========================================
    // DÉPARTEMENT & ENTREPRISE
    // ========================================

    public function getDepartement(): ?Departement { return $this->departement; }
    public function setDepartement(?Departement $departement): static { $this->departement = $departement; return $this; }

    public function getEntreprise(): ?Entreprise
    {
        return $this->departement?->getEntreprise();
    }

    public function appartientAEntreprise(Entreprise $entreprise): bool
    {
        return $this->getEntreprise()?->getId() === $entreprise->getId();
    }
}