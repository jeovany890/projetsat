<?php

namespace App\Service;

use App\Entity\CampagnePhishing;
use App\Entity\ResultatPhishing;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PhishingService
{
    public function __construct(
        private EmailService $emailService,
        private EntityManagerInterface $em,
        private UrlGeneratorInterface $urlGenerator
    ) {}

    public function envoyerPourResultat(ResultatPhishing $resultat): bool
    {
        $token    = $resultat->getJetonTrackingUnique();
        $campagne = $resultat->getCampagne();
        $gabarit  = $campagne->getGabarit();
        $employe  = $resultat->getEmploye();

        $urlSignalement = $this->urlGenerator->generate(
            'phishing_signal',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
        $urlFakePage = $this->urlGenerator->generate(
            'phishing_fake_page',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
        $urlClic = $this->urlGenerator->generate(
            'phishing_track_click',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // ── Logo en base64 inline ──────────────────────────────────
        // On lit le fichier logo sur le disque, on l'encode en base64
        // et on l'injecte directement dans l'attribut src de l'image.
        //
        // Avantages vs CID ou URL externe :
        //   - Gmail affiche l'image IMMÉDIATEMENT, même avant ouverture
        //   - Pas de pièce jointe visible (icône "logo.jpeg" supprimée)
        //   - Fonctionne hors ligne, sans serveur, sans ngrok
        //   - Compatible Gmail, Outlook, Yahoo, Apple Mail
        $logoDataUri = $this->genererLogoDataUri($employe, $gabarit);

        $contenu = $this->personnaliserContenu(
            $gabarit->getContenuHtml(),
            $employe,
            $urlSignalement,
            $urlFakePage,
            $urlClic,
            $token,
            $logoDataUri
        );

        // Pas d'embeds CID — tout est dans le HTML
        $this->emailService->envoyerEmailPhishing(
            destinataire:    $resultat->getEmailDestinataire(),
            sujet:           $gabarit->getSujetEmail(),
            contenuHtml:     $contenu,
            nomExpediteur:   $gabarit->getNomExpediteur(),
            emailExpediteur: $gabarit->getEmailExpediteur(),
            compteEmailDsn:  $gabarit->getCompteEmailDsn(),
        );

        $resultat->marquerCommeEnvoye();
        return true;
    }

    public function lancerCampagne(CampagnePhishing $campagne): array
    {
        $campagne->setStatut('EN_COURS');
        if (!$campagne->getDateDebut()) {
            $campagne->setDateDebut(new \DateTime());
        }

        $resultats = $this->em->getRepository(ResultatPhishing::class)
            ->findPlanifiesPourCampagne($campagne);

        $envoyes = 0;
        $echoues = 0;
        $erreurs = [];

        foreach ($resultats as $resultat) {
            try {
                $this->envoyerPourResultat($resultat);
                $this->em->flush();
                $envoyes++;
            } catch (\Throwable $e) {
                $resultat->marquerCommeEchoue($e->getMessage());
                $this->em->flush();
                $echoues++;
                $erreurs[] = $e->getMessage();
            }
        }

        return ['envoyes' => $envoyes, 'echoues' => $echoues, 'erreurs' => $erreurs];
    }

    // ══════════════════════════════════════════════════════════════
    // GÉNÉRATION DU DATA URI BASE64
    //
    // Retourne une chaîne du type :
    //   data:image/jpeg;base64,/9j/4AAQSkZJRgAB...
    //
    // Cette chaîne est mise directement dans src="..." de l'image.
    // Gmail/Outlook/Yahoo affichent l'image sans aucune restriction
    // car elle est physiquement dans le HTML de l'email.
    //
    // L'image est redimensionnée à max 200x80px pour réduire le poids.
    // ══════════════════════════════════════════════════════════════
    private function genererLogoDataUri(
        \App\Entity\Employe         $employe,
        \App\Entity\GabaritPhishing $gabarit
    ): string {
        $logoPath = $this->resoudreLogoChemin($employe, $gabarit);

        if (!$logoPath || !file_exists($logoPath)) {
            // Retourner un pixel transparent si pas de logo
            return 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
        }

        // Redimensionner l'image si les fonctions GD sont disponibles
        $imageData = $this->redimensionnerImage($logoPath);

        $mime   = $this->detectMime($logoPath);
        $base64 = base64_encode($imageData);

        return "data:{$mime};base64,{$base64}";
    }

    // ══════════════════════════════════════════════════════════════
    // REDIMENSIONNEMENT AVEC GD (disponible sur AlwaysData)
    // Réduit l'image à max 200x80px pour garder l'email léger.
    // Si GD n'est pas disponible, retourne le fichier brut.
    // ══════════════════════════════════════════════════════════════
    private function redimensionnerImage(string $path): string
    {
        if (!extension_loaded('gd')) {
            return file_get_contents($path);
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $src = match($ext) {
            'png'  => @imagecreatefrompng($path),
            'gif'  => @imagecreatefromgif($path),
            'webp' => @imagecreatefromwebp($path),
            default => @imagecreatefromjpeg($path),
        };

        if (!$src) {
            return file_get_contents($path);
        }

        $origW = imagesx($src);
        $origH = imagesy($src);

        // Calculer les nouvelles dimensions (max 200x80)
        $maxW = 200;
        $maxH = 80;
        $ratio = min($maxW / $origW, $maxH / $origH);

        // Si l'image est déjà petite, pas besoin de redimensionner
        if ($ratio >= 1) {
            imagedestroy($src);
            return file_get_contents($path);
        }

        $newW = (int)($origW * $ratio);
        $newH = (int)($origH * $ratio);

        $dst = imagecreatetruecolor($newW, $newH);

        // Fond blanc pour les PNG avec transparence
        $blanc = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $blanc);

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

        ob_start();
        imagejpeg($dst, null, 85);
        $data = ob_get_clean();

        imagedestroy($src);
        imagedestroy($dst);

        return $data;
    }

    // ══════════════════════════════════════════════════════════════
    // RÉSOLUTION DU CHEMIN LOCAL DU LOGO
    //
    // Priorité :
    //   1. logoPath sur l'entité Entreprise
    //   2. logoPath sur le gabarit
    //   3. public/images/logo.jpeg (logo par défaut)
    // ══════════════════════════════════════════════════════════════
    private function resoudreLogoChemin(
        \App\Entity\Employe         $employe,
        \App\Entity\GabaritPhishing $gabarit
    ): ?string {
        $publicDir = dirname(__DIR__, 2) . '/public';

        // 1. Logo uploadé sur l'entreprise
        $entreprise = $employe->getEntreprise();
        if ($entreprise) {
            if (method_exists($entreprise, 'getLogoPath') && $entreprise->getLogoPath()) {
                $path = $publicDir . '/' . ltrim($entreprise->getLogoPath(), '/');
                if (file_exists($path)) return $path;
            }
        }

        // 2. Logo du gabarit
        if (method_exists($gabarit, 'getLogoPath') && $gabarit->getLogoPath()) {
            $path = $publicDir . '/' . ltrim($gabarit->getLogoPath(), '/');
            if (file_exists($path)) return $path;
        }

        // 3. Logo par défaut
        $default = $publicDir . '/images/logo.jpeg';
        if (file_exists($default)) return $default;

        return null;
    }

    private function detectMime(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match($ext) {
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'svg'  => 'image/svg+xml',
            default => 'image/jpeg',
        };
    }

    // ══════════════════════════════════════════════════════════════
    // PERSONNALISATION DU HTML
    // {{LOGO_ENTREPRISE}} → data:image/jpeg;base64,...
    // ══════════════════════════════════════════════════════════════
    private function personnaliserContenu(
        string $contenu,
        \App\Entity\Employe $employe,
        string $urlSignalement,
        string $urlFakePage,
        string $urlClic,
        string $token,
        string $logoDataUri
    ): string {
        $now        = new \DateTime();
        $entreprise = $employe->getEntreprise()?->getNom() ?? 'SAT Platform';

        return str_replace(
            [
                '{{LOGO_ENTREPRISE}}', '{LOGO_ENTREPRISE}',
                '{{LOGO_URL}}',        '{LOGO_URL}',
                '{{LIEN_SIGNALEMENT}}', '{LIEN_SIGNALEMENT}',
                '{{LIEN_PIEGE}}',       '{LIEN_PIEGE}',
                '{{LIEN_CLIC}}',        '{LIEN_CLIC}',
                '{{PRENOM_EMPLOYE}}', '{PRENOM_EMPLOYE}', '{{PRENOM}}', '{PRENOM}',
                '{{NOM_EMPLOYE}}',    '{NOM_EMPLOYE}',    '{{NOM}}',    '{NOM}',
                '{{NOM_COMPLET}}',    '{NOM_COMPLET}',
                '{{EMAIL_EMPLOYE}}',  '{EMAIL_EMPLOYE}',  '{{EMAIL}}',  '{EMAIL}',
                '{{POSTE_EMPLOYE}}',  '{POSTE_EMPLOYE}',  '{{POSTE}}',  '{POSTE}',
                '{{ENTREPRISE}}',     '{ENTREPRISE}',
                '{{TOKEN}}',          '{TOKEN}',
                '{{DATE_ACTUELLE}}',  '{DATE_ACTUELLE}',
                '{{HEURE_ACTUELLE}}', '{HEURE_ACTUELLE}',
                '{{DATE_LIMITE}}',    '{DATE_LIMITE}',
                '{{ "now"|date("YmdHi") }}',
                '{{ "now"|date("dm") }}',
                '{{ "now"|date_modify("+24 hours")|date("d/m/Y à H:i") }}',
            ],
            [
                $logoDataUri, $logoDataUri,
                $logoDataUri, $logoDataUri,
                $urlSignalement, $urlSignalement,
                $urlFakePage,    $urlFakePage,
                $urlClic,        $urlClic,
                $employe->getPrenom(), $employe->getPrenom(), $employe->getPrenom(), $employe->getPrenom(),
                $employe->getNom(),    $employe->getNom(),    $employe->getNom(),    $employe->getNom(),
                $employe->getPrenom() . ' ' . $employe->getNom(),
                $employe->getPrenom() . ' ' . $employe->getNom(),
                $employe->getEmail(), $employe->getEmail(), $employe->getEmail(), $employe->getEmail(),
                $employe->getPoste() ?? 'Employé',
                $employe->getPoste() ?? 'Employé',
                $employe->getPoste() ?? 'Employé',
                $employe->getPoste() ?? 'Employé',
                $entreprise, $entreprise,
                $token, $token,
                $now->format('d/m/Y'), $now->format('d/m/Y'),
                $now->format('H:i'),   $now->format('H:i'),
                (clone $now)->modify('+24 hours')->format('d/m/Y à H:i'),
                (clone $now)->modify('+24 hours')->format('d/m/Y à H:i'),
                $now->format('YmdHi'),
                $now->format('dm'),
                (clone $now)->modify('+24 hours')->format('d/m/Y à H:i'),
            ],
            $contenu
        );
    }
}