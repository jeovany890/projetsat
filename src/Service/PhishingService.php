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

        // Tracking par clic uniquement — le pixel est bloqué par Gmail
        $urlClic = $this->urlGenerator->generate('phishing_track_click', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);

        $contenu = $this->personnaliserContenu(
            $gabarit->getContenuHtml(),
            $employe,
            $urlClic,
            $token
        );

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
            [
                '{LIEN_PIEGE}',    '{{LIEN_PIEGE}}',
                '{TOKEN}',         '{{TOKEN}}',
                '{PRENOM}',        '{{PRENOM_EMPLOYE}}',
                '{NOM}',           '{{NOM_EMPLOYE}}',
                '{NOM_COMPLET}',   '{{NOM_COMPLET}}',
                '{EMAIL}',         '{{EMAIL_EMPLOYE}}',
                '{POSTE}',         '{{POSTE_EMPLOYE}}',
            ],
            [
                $urlClic,          $urlClic,
                $token,            $token,
                $employe->getPrenom(), $employe->getPrenom(),
                $employe->getNom(),    $employe->getNom(),
                $employe->getPrenom() . ' ' . $employe->getNom(),
                $employe->getPrenom() . ' ' . $employe->getNom(),
                $employe->getEmail(),  $employe->getEmail(),
                $employe->getPoste() ?? 'Employé',
                $employe->getPoste() ?? 'Employé',
            ],
            $contenu
        );
    }
}