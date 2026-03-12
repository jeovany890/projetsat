<?php

namespace App\Service;

use App\Entity\CampagnePhishing;
use App\Entity\EnvoiPhishing;
use App\Entity\ResultatPhishing;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Service d'envoi phishing — utilisé par CampagnePhishingController::lancer()
 * et potentiellement par une commande CLI de retry.
 */
class PhishingService
{
    public function __construct(
        private EmailService $emailService,
        private EntityManagerInterface $em,
        private UrlGeneratorInterface $urlGenerator
    ) {}

    /**
     * Envoie un email phishing pour un EnvoiPhishing donné.
     * Suppose que envoi->getResultat() existe et contient le token.
     */
    public function envoyerPourEnvoi(EnvoiPhishing $envoi): bool
    {
        $resultat = $envoi->getResultat();
        if (!$resultat) {
            throw new \LogicException("EnvoiPhishing ID {$envoi->getId()} n'a pas de ResultatPhishing associé.");
        }

        $token    = $resultat->getJetonTrackingUnique();
        $campagne = $envoi->getCampagne();
        $gabarit  = $campagne->getGabarit();
        $employe  = $envoi->getEmploye();

        $urlPixel = $this->urlGenerator->generate('phishing_track_email', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
        $urlClic  = $this->urlGenerator->generate('phishing_track_click', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);

        $contenu = $this->personnaliserContenu(
            $gabarit->getContenuHtml(),
            $employe,
            $urlClic,
            $token
        );
        // ── TRACKING MULTI-MÉTHODES ──
        $pixelImg     = "<img src=\"{$urlPixel}\" width=\"1\" height=\"1\""
            . " style=\"display:none;border:0;position:absolute;\" alt=\"\">";
        $pixelCss     = "<div style=\"background-image:url('{$urlPixel}');"
            . "width:1px;height:1px;position:absolute;opacity:0;overflow:hidden;font-size:0;\"></div>";
        $pixelPreload = "<link rel=\"preload\" as=\"image\" href=\"{$urlPixel}\">";

        if (stripos($contenu, '</head>') !== false) {
            $contenu = str_ireplace('</head>', $pixelPreload . '</head>', $contenu);
        } else {
            $contenu = $pixelPreload . $contenu;
        }
        $contenu .= $pixelImg . $pixelCss;

        $this->emailService->envoyerEmailPhishing(
            destinataire:    $envoi->getEmailDestinataire(),
            sujet:           $gabarit->getSujetEmail(),
            contenuHtml:     $contenu,
            nomExpediteur:   $gabarit->getNomExpediteur(),
            emailExpediteur: $gabarit->getEmailExpediteur(),
            compteEmailDsn:  $gabarit->getCompteEmailDsn()
        );

        $envoi->marquerCommeEnvoye();
        $resultat->setEmailEnvoye(true)->setDateEnvoi(new \DateTime());
        $campagne->incrementerEmailsEnvoyes();

        return true;
    }

    private function personnaliserContenu(
        string $contenu,
        \App\Entity\Employe $employe,
        string $urlClic,
        string $token
    ): string {
        return str_replace(
            ['{LIEN_PIEGE}', '{{LIEN_PIEGE}}', '{TOKEN}', '{{TOKEN}}',
             '{PRENOM}', '{NOM}', '{NOM_COMPLET}', '{EMAIL}', '{POSTE}'],
            [$urlClic, $urlClic, $token, $token,
             $employe->getPrenom(),
             $employe->getNom(),
             $employe->getPrenom() . ' ' . $employe->getNom(),
             $employe->getEmail(),
             $employe->getPoste() ?? 'Employé'],
            $contenu
        );
    }
}