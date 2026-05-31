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

        // ── Résoudre les images à embarquer en CID ─────────────────
        // On construit la liste des images à passer à PHPMailer.
        // Chaque image aura un Content-ID unique.
        // Dans le HTML du gabarit on utilise src="cid:logo_entreprise"
        // Gmail affiche les images CID sans aucune restriction.
        $embeds  = [];
        $logoSrc = '';

        $logoPath = $this->resoudreLogoChemin($employe, $gabarit);
        if ($logoPath) {
            $embeds[]  = [
                'path' => $logoPath,
                'cid'  => 'logo_entreprise',
                'name' => basename($logoPath),
                'mime' => $this->detectMime($logoPath),
            ];
            $logoSrc = 'cid:logo_entreprise';
        }

        $contenu = $this->personnaliserContenu(
            $gabarit->getContenuHtml(),
            $employe,
            $urlSignalement,
            $urlFakePage,
            $urlClic,
            $token,
            $logoSrc
        );

        $this->emailService->envoyerEmailPhishing(
            destinataire:    $resultat->getEmailDestinataire(),
            sujet:           $gabarit->getSujetEmail(),
            contenuHtml:     $contenu,
            nomExpediteur:   $gabarit->getNomExpediteur(),
            emailExpediteur: $gabarit->getEmailExpediteur(),
            compteEmailDsn:  $gabarit->getCompteEmailDsn(),
            embeds:          $embeds,    // ← images embarquées CID
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
    // RÉSOLUTION DU CHEMIN LOCAL DU LOGO
    //
    // Retourne le chemin ABSOLU sur le disque (pas une URL).
    // PHPMailer lira le fichier et l'embarquera en base64 dans l'email.
    //
    // Priorité :
    //   1. logoPath sur l'entité Entreprise  (ex: "uploads/logos/xxx.jpg")
    //   2. Fichier dans public/images/ dont le nom correspond au slug/nom
    //   3. Logo par défaut : public/images/logo.jpeg
    // ══════════════════════════════════════════════════════════════
    private function resoudreLogoChemin(
        \App\Entity\Employe       $employe,
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

        // 2. Logo du gabarit (champ logoPath si présent)
        if (method_exists($gabarit, 'getLogoPath') && $gabarit->getLogoPath()) {
            $path = $publicDir . '/' . ltrim($gabarit->getLogoPath(), '/');
            if (file_exists($path)) return $path;
        }

        // 3. Logo par défaut (logo.jpeg)
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
    //
    // Dans le gabarit HTML utilise :
    //   src="{{LOGO_ENTREPRISE}}"
    // Ce placeholder sera remplacé par "cid:logo_entreprise"
    // Gmail affiche cette image directement sans blocage.
    // ══════════════════════════════════════════════════════════════
    private function personnaliserContenu(
        string $contenu,
        \App\Entity\Employe $employe,
        string $urlSignalement,
        string $urlFakePage,
        string $urlClic,
        string $token,
        string $logoSrc
    ): string {
        $now        = new \DateTime();
        $entreprise = $employe->getEntreprise()?->getNom() ?? 'SAT Platform';

        return str_replace(
            [
                // Logo CID (affiché sans blocage Gmail)
                '{{LOGO_ENTREPRISE}}', '{LOGO_ENTREPRISE}',
                '{{LOGO_URL}}',        '{LOGO_URL}',

                // Liens tracking
                '{{LIEN_SIGNALEMENT}}', '{LIEN_SIGNALEMENT}',
                '{{LIEN_PIEGE}}',       '{LIEN_PIEGE}',
                '{{LIEN_CLIC}}',        '{LIEN_CLIC}',

                // Employé
                '{{PRENOM_EMPLOYE}}', '{PRENOM_EMPLOYE}', '{PRENOM}', '{{PRENOM}}',
                '{{NOM_EMPLOYE}}',    '{NOM_EMPLOYE}',    '{NOM}',    '{{NOM}}',
                '{{NOM_COMPLET}}',    '{NOM_COMPLET}',
                '{{EMAIL_EMPLOYE}}',  '{EMAIL_EMPLOYE}',  '{EMAIL}',  '{{EMAIL}}',
                '{{POSTE_EMPLOYE}}',  '{POSTE_EMPLOYE}',  '{POSTE}',  '{{POSTE}}',
                '{{ENTREPRISE}}',     '{ENTREPRISE}',

                // Token
                '{{TOKEN}}', '{TOKEN}',

                // Date/heure
                '{{DATE_ACTUELLE}}',  '{DATE_ACTUELLE}',
                '{{HEURE_ACTUELLE}}', '{HEURE_ACTUELLE}',
                '{{DATE_LIMITE}}',    '{DATE_LIMITE}',

                // Legacy Twig
                '{{ "now"|date("YmdHi") }}',
                '{{ "now"|date("dm") }}',
                '{{ "now"|date_modify("+24 hours")|date("d/m/Y à H:i") }}',
            ],
            [
                // Logo → CID (embarqué dans le MIME, affiché sans restriction)
                $logoSrc, $logoSrc,
                $logoSrc, $logoSrc,

                // Liens
                $urlSignalement, $urlSignalement,
                $urlFakePage,    $urlFakePage,
                $urlClic,        $urlClic,

                // Employé
                $employe->getPrenom(), $employe->getPrenom(), $employe->getPrenom(), $employe->getPrenom(),
                $employe->getNom(),    $employe->getNom(),    $employe->getNom(),    $employe->getNom(),
                $employe->getPrenom() . ' ' . $employe->getNom(),
                $employe->getPrenom() . ' ' . $employe->getNom(),
                $employe->getEmail(),  $employe->getEmail(),  $employe->getEmail(),  $employe->getEmail(),
                $employe->getPoste() ?? 'Employé',
                $employe->getPoste() ?? 'Employé',
                $employe->getPoste() ?? 'Employé',
                $employe->getPoste() ?? 'Employé',
                $entreprise, $entreprise,

                // Token
                $token, $token,

                // Date/heure
                $now->format('d/m/Y'), $now->format('d/m/Y'),
                $now->format('H:i'),   $now->format('H:i'),
                (clone $now)->modify('+24 hours')->format('d/m/Y à H:i'),
                (clone $now)->modify('+24 hours')->format('d/m/Y à H:i'),

                // Legacy
                $now->format('YmdHi'),
                $now->format('dm'),
                (clone $now)->modify('+24 hours')->format('d/m/Y à H:i'),
            ],
            $contenu
        );
    }
}