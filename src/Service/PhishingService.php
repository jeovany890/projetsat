<?php

namespace App\Service;

use App\Entity\CampagnePhishing;
use App\Entity\ResultatPhishing;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PhishingService
{
    public function __construct(
        private EmailService            $emailService,
        private EntityManagerInterface  $em,
        private UrlGeneratorInterface   $urlGenerator,
        private LoggerInterface         $logger
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

        // Log pour diagnostic — visible dans var/log/prod.log
        $this->logger->info('PhishingService logo résolu', [
            'gabarit'       => $gabarit->getTitre(),
            'logoPath_bd'   => $gabarit->getLogoPath(),
            'logoUrl_final' => $logoUrl,
            'APP_URL_env'   => $_ENV['APP_URL'] ?? 'NON DÉFINI',
        ]);

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
                $this->logger->error('Erreur envoi phishing', ['message' => $e->getMessage()]);
                $resultat->marquerCommeEchoue($e->getMessage());
                $this->em->flush();
                $echoues++;
                $erreurs[] = $e->getMessage();
            }
        }

        return ['envoyes' => $envoyes, 'echoues' => $echoues, 'erreurs' => $erreurs];
    }

    private function resoudreLogoUrl(
        \App\Entity\Employe         $employe,
        \App\Entity\GabaritPhishing $gabarit
    ): string {
        $baseUrl = rtrim($_ENV['APP_URL'] ?? 'https://satplatform.alwaysdata.net', '/');

        // 1. Logo uploadé sur le gabarit
        if ($gabarit->getLogoPath()) {
            $url = $baseUrl . '/' . ltrim($gabarit->getLogoPath(), '/');
            $this->logger->debug('Logo depuis gabarit', ['url' => $url]);
            return $url;
        }

        // 2. Logo de l'entreprise ciblée
        $entreprise = $employe->getEntreprise();
        if ($entreprise) {
            if (method_exists($entreprise, 'getLogoPath') && $entreprise->getLogoPath()) {
                $url = $baseUrl . '/' . ltrim($entreprise->getLogoPath(), '/');
                $this->logger->debug('Logo depuis entreprise', ['url' => $url]);
                return $url;
            }
        }

        // 3. Logo par défaut
        $url = $baseUrl . '/images/logo.jpeg';
        $this->logger->debug('Logo par défaut', ['url' => $url]);
        return $url;
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