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

        $urlSignalement = $this->urlGenerator->generate('phishing_signal', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
        $urlFakePage    = $this->urlGenerator->generate('phishing_fake_page', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
        $urlClic        = $this->urlGenerator->generate('phishing_track_click', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);

        $contenu = $this->personnaliserContenu(
            $gabarit->getContenuHtml(),
            $employe,
            $urlSignalement,
            $urlFakePage,
            $urlClic,
            $token
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

        $resultats = $this->em->getRepository(ResultatPhishing::class)->findPlanifiesPourCampagne($campagne);

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

    private function personnaliserContenu(
        string $contenu,
        \App\Entity\Employe $employe,
        string $urlSignalement,
        string $urlFakePage,
        string $urlClic,
        string $token
    ): string {
        $now   = new \DateTime();
        $entreprise = $employe->getEntreprise() ? $employe->getEntreprise()->getNom() : 'SAT Platform';

        return str_replace(
            [
                '{{LIEN_SIGNALEMENT}}', '{LIEN_SIGNALEMENT}',
                '{{LIEN_PIEGE}}',       '{LIEN_PIEGE}',
                '{{PRENOM_EMPLOYE}}',   '{PRENOM_EMPLOYE}', '{PRENOM}',
                '{{NOM_EMPLOYE}}',      '{NOM_EMPLOYE}',    '{NOM}',
                '{{NOM_COMPLET}}',      '{NOM_COMPLET}',
                '{{EMAIL_EMPLOYE}}',    '{EMAIL_EMPLOYE}', '{EMAIL}',
                '{{POSTE_EMPLOYE}}',    '{POSTE_EMPLOYE}', '{POSTE}',
                '{{TOKEN}}',            '{TOKEN}',
                '{{DATE_ACTUELLE}}',    '{DATE_ACTUELLE}',
                '{{HEURE_ACTUELLE}}',   '{HEURE_ACTUELLE}',
                '{{ENTREPRISE}}',       '{ENTREPRISE}',
                '{{ "now"|date("YmdHi") }}',
                '{{ "now"|date("dm") }}',
                '{{ "now"|date_modify("+24 hours")|date("d/m/Y à H:i") }}',
            ],
            [
                $urlSignalement, $urlSignalement,
                $urlFakePage,    $urlFakePage,
                $employe->getPrenom(), $employe->getPrenom(), $employe->getPrenom(),
                $employe->getNom(),    $employe->getNom(),    $employe->getNom(),
                $employe->getPrenom() . ' ' . $employe->getNom(),
                $employe->getPrenom() . ' ' . $employe->getNom(),
                $employe->getEmail(),  $employe->getEmail(),
                $employe->getPoste() ?? 'Employé', $employe->getPoste() ?? 'Employé', $employe->getPoste() ?? 'Employé',
                $token,            $token,
                $now->format('d/m/Y'), $now->format('d/m/Y'),
                $now->format('H:i'),  $now->format('H:i'),
                $entreprise, $entreprise,
                $now->format('YmdHi'),
                $now->format('dm'),
                (clone $now)->modify('+24 hours')->format('d/m/Y à H:i'),
            ],
            $contenu
        );
    }
}
