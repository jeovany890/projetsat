<?php

namespace App\Service;

use App\Entity\CampagnePhishing;
use App\Entity\EnvoiPhishing;
use App\Entity\Employe;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PhishingService
{
    public function __construct(
        private EmailService $emailService,
        private EntityManagerInterface $em,
        private UrlGeneratorInterface $urlGenerator,
        private string $baseUrl
    ) {}

    public function envoyerEmailPhishing(CampagnePhishing $campagne, Employe $employe): EnvoiPhishing
    {
        // Créer l'envoi avec un token unique
        $envoi = new EnvoiPhishing();
        $envoi->setCampagne($campagne);
        $envoi->setEmploye($employe);
        $envoi->setToken(bin2hex(random_bytes(32)));
        $envoi->setDateEnvoi(new \DateTime());

        $this->em->persist($envoi);
        $this->em->flush();

        // Générer les liens de tracking
        $lienClic = $this->baseUrl . $this->urlGenerator->generate(
            'phishing_track_click',
            ['token' => $envoi->getToken()]
        );

        $lienPixel = $this->baseUrl . $this->urlGenerator->generate(
            'phishing_track_email',
            ['token' => $envoi->getToken()]
        );

        // Personnaliser le contenu HTML
        $gabarit = $campagne->getGabarit();
        $contenuHtml = $gabarit->getContenuHtml();

        // Remplacer {LIEN_PIEGE}
        $contenuHtml = str_replace('{LIEN_PIEGE}', $lienClic, $contenuHtml);

        // Ajouter le pixel de tracking
        $pixelTracking = "<img src=\"{$lienPixel}\" alt=\"\" width=\"1\" height=\"1\" style=\"display:none;\">";
        $contenuHtml .= $pixelTracking;

        // Personnaliser avec les données employé
        $contenuHtml = $this->personnaliserContenu($contenuHtml, $employe);

        // Envoyer l'email
        try {
            $this->emailService->envoyerEmailPhishing(
                $employe->getEmail(),
                $gabarit->getSujetEmail(),
                $contenuHtml,
                $gabarit->getNomExpediteur(),
                $gabarit->getEmailExpediteur(),
                $gabarit->getCompteEmailDsn()
            );

            $envoi->setEstEnvoye(true);
        } catch (\Exception $e) {
            $envoi->setEstEnvoye(false);
            $envoi->setMessageErreur($e->getMessage());
        }

        $this->em->flush();

        return $envoi;
    }

    private function personnaliserContenu(string $contenu, Employe $employe): string
    {
        $variables = [
            '{PRENOM}' => $employe->getPrenom(),
            '{NOM}' => $employe->getNom(),
            '{NOM_COMPLET}' => $employe->getNomComplet(),
            '{EMAIL}' => $employe->getEmail(),
            '{POSTE}' => $employe->getPoste() ?? 'Employé',
        ];

        return str_replace(
            array_keys($variables),
            array_values($variables),
            $contenu
        );
    }
}