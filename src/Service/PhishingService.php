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

        $logoUrl = $this->resoudreLogoUrl($employe, $gabarit);

        $contenu = $this->personnaliserContenu(
            $gabarit->getContenuHtml(),
            $employe,
            $urlSignalement,
            $urlFakePage,
            $urlClic,
            $token,
            $logoUrl
        );

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

        $envoyes = 0; $echoues = 0; $erreurs = [];

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
    // RÉSOLUTION DU LOGO
    //
    // Priorité :
    //   1. Logo uploadé sur le gabarit  → spécifique au gabarit
    //      (BOA, MTN, Orange, SBEE...)
    //   2. Logo de l'entreprise ciblée  → si le gabarit n'en a pas
    //   3. Logo par défaut              → public/images/logo.jpeg
    // ══════════════════════════════════════════════════════════════
    private function resoudreLogoUrl(
        \App\Entity\Employe         $employe,
        \App\Entity\GabaritPhishing $gabarit
    ): string {
        $baseUrl = rtrim($_ENV['APP_URL'] ?? 'https://satplatform.alwaysdata.net', '/');

        // 1. Logo du gabarit (prioritaire — spécifique au scénario)
        if ($gabarit->getLogoPath()) {
            return $baseUrl . '/' . ltrim($gabarit->getLogoPath(), '/');
        }

        // 2. Logo de l'entreprise ciblée
        $entreprise = $employe->getEntreprise();
        if ($entreprise) {
            if (method_exists($entreprise, 'getLogoPath') && $entreprise->getLogoPath()) {
                return $baseUrl . '/' . ltrim($entreprise->getLogoPath(), '/');
            }
        }

        // 3. Logo par défaut
        return $baseUrl . '/images/logo.jpeg';
    }

    private function personnaliserContenu(
        string $contenu,
        \App\Entity\Employe $employe,
        string $urlSignalement,
        string $urlFakePage,
        string $urlClic,
        string $token,
        string $logoUrl
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
                $logoUrl, $logoUrl, $logoUrl, $logoUrl,
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