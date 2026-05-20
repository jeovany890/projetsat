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
#[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
private ?Entreprise $entreprise = null;

    // 2FA OTP
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $codeOtp = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $codeOtpExpiration = null;

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

    public function getCodeOtp(): ?string { return $this->codeOtp; }
    public function setCodeOtp(?string $codeOtp): static { $this->codeOtp = $codeOtp; return $this; }
    public function getCodeOtpExpiration(): ?\DateTimeInterface { return $this->codeOtpExpiration; }
    public function setCodeOtpExpiration(?\DateTimeInterface $exp): static { $this->codeOtpExpiration = $exp; return $this; }

    public function genererCodeOtp(): string
    {
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->codeOtp = $code;
        $this->codeOtpExpiration = (new \DateTime())->modify('+10 minutes');
        return $code;
    }

    public function isCodeOtpValide(string $code): bool
    {
        return $this->codeOtp === $code
            && $this->codeOtpExpiration !== null
            && $this->codeOtpExpiration > new \DateTime();
    }

    public function effacerCodeOtp(): void
    {
        $this->codeOtp = null;
        $this->codeOtpExpiration = null;
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