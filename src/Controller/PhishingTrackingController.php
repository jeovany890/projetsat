<?php

namespace App\Controller;

use App\Entity\CampagnePhishing;
use App\Entity\ModuleFormation;
use App\Entity\ProgressionModule;
use App\Entity\ResultatPhishing;
use App\Entity\SignalementPhishing;
use App\Repository\CampagnePhishingRepository;
use App\Repository\ResultatPhishingRepository;
use App\Service\ScoringMoteurService;
use App\Service\ScoringResultat;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PhishingTrackingController extends AbstractController
{
    private const SOUMISSION_DELAY_MINUTES = 10;

    public function __construct(private ScoringMoteurService $scoring) {}

    // ═════════════════════════════════════

    // ══════════════════════════════════════════
    // TRACK CLIC SUR LIEN PHISHING
    // Pénalité : -15 vigilance
    // ══════════════════════════════════════════
    #[Route('/track/click/{token}', name: 'phishing_track_click')]
    public function trackClick(string $token, EntityManagerInterface $em): Response
    {
        $resultat = $em->getRepository(ResultatPhishing::class)
            ->findOneBy(['jetonTrackingUnique' => $token]);

        if (!$resultat) {
            return $this->render('phishing/erreur.html.twig');
        }

        // Pénalité appliquée uniquement au premier clic
        if (!$resultat->isLienClique()) {
            $resultat->setLienClique(true);
            $resultat->setDateClic(new \DateTime());

            $employe   = $resultat->getEmploye();
            $categorie = $resultat->getCampagne()->getGabarit()->getCategorie();

            // -10 vigilance via moteur de scoring
            $this->scoring->phishingClic($employe, $categorie);

            $em->flush();
        }

        return $this->redirectToRoute('phishing_fake_page', ['token' => $token]);
    }

    // ══════════════════════════════════════════
    // SOUMISSION DE DONNÉES (grave)
    // IMPORTANT : cette route DOIT être déclarée
    // AVANT phishing_fake_page pour éviter que
    // Symfony interprète "submit" comme un {token}
    // Pénalité : -25 vigilance
    // ══════════════════════════════════════════
    #[Route('/phishing/fake/submit', name: 'phishing_fake_submit', methods: ['POST'])]
    public function fakeSubmit(Request $request, EntityManagerInterface $em): Response
    {
        $token = $request->request->get('token');
        if (!$token) {
            return $this->redirectToRoute('employe_dashboard');
        }

        $resultat = $em->getRepository(ResultatPhishing::class)
            ->findOneBy(['jetonTrackingUnique' => $token]);

        if (!$resultat) {
            return $this->render('phishing/erreur.html.twig');
        }

        // Déjà soumis → page alerte directement
        if ($resultat->isSoumissionDonnees()) {
            return $this->redirectToRoute('phishing_alerte', ['token' => $token]);
        }

        // Vérifier le délai depuis le clic
        $dateClic = $resultat->getDateClic();
        if (!$dateClic) {
            return $this->redirectToRoute('phishing_alerte', ['token' => $token]);
        }

        $diffSecondes = (new \DateTime())->getTimestamp() - $dateClic->getTimestamp();
        if ($diffSecondes / 60 > self::SOUMISSION_DELAY_MINUTES) {
            return $this->redirectToRoute('phishing_alerte', ['token' => $token]);
        }

        $employe   = $resultat->getEmploye();
        $categorie = $resultat->getCampagne()->getGabarit()->getCategorie();

        $resultat->setSoumissionDonnees(true);
        if (method_exists($resultat, 'marquerSoumissionDonnees')) {
            $resultat->marquerSoumissionDonnees();
        }

        // -25 vigilance + module recommandé automatiquement assigné
        $scoringResultat = $this->scoring->phishingSoumission($employe, $categorie);

        if ($scoringResultat->aUnModulePropose()) {
            $this->assignerFormation($employe, $scoringResultat->modulePropose, $em);
        }

        $em->flush();

        return $this->redirectToRoute('phishing_alerte', ['token' => $token]);
    }

    // ══════════════════════════════════════════
    // FAKE PAGE (formulaire de collecte)
    // IMPORTANT : déclarée APRÈS phishing_fake_submit
    // ══════════════════════════════════════════
    #[Route('/phishing/fake/{token}', name: 'phishing_fake_page')]
    public function fakePage(string $token, EntityManagerInterface $em): Response
    {
        $resultat = $em->getRepository(ResultatPhishing::class)
            ->findOneBy(['jetonTrackingUnique' => $token]);

        if (!$resultat) {
            return $this->render('phishing/erreur.html.twig');
        }

        // Enregistrer le clic si accès direct à la fake page
        if (!$resultat->isLienClique()) {
            $resultat->setLienClique(true);
            $resultat->setDateClic(new \DateTime());

            $employe   = $resultat->getEmploye();
            $categorie = $resultat->getCampagne()->getGabarit()->getCategorie();
            $this->scoring->phishingClic($employe, $categorie);
            $em->flush();
        }

        $gabarit = $resultat->getCampagne()->getGabarit();
        $slug    = strtolower($gabarit->getSlug() ?? '');

        if (str_contains($slug, 'rssi') || str_contains($slug, 'renouvellement') || str_contains($slug, 'motdepasse')) {
            $template = 'phishing/fake_rssi.html.twig';
        } elseif (str_contains($slug, 'boa') || str_contains($slug, 'apex')) {
            $template = 'phishing/fake_boa.html.twig';
        } elseif (str_contains($slug, 'ecobank') || str_contains($slug, 'ecoconnect')) {
            $template = 'phishing/fake_ecobank.html.twig';
        } else {
            $template = 'phishing/fake_generic.html.twig';
        }

        return $this->render($template, [
            'token'   => $token,
            'gabarit' => $gabarit,
            'employe' => $resultat->getEmploye(),
        ]);
    }

    // ══════════════════════════════════════════
    // PAGE ALERTE — après clic ou soumission
    // ══════════════════════════════════════════
    #[Route('/phishing-alerte/{token}', name: 'phishing_alerte')]
    public function alerte(string $token, EntityManagerInterface $em): Response
    {
        $resultat = $em->getRepository(ResultatPhishing::class)
            ->findOneBy(['jetonTrackingUnique' => $token]);

        if (!$resultat) {
            return $this->render('phishing/erreur.html.twig');
        }

        $employe   = $resultat->getEmploye();
        $gabarit   = $resultat->getCampagne()->getGabarit();
        $categorie = $gabarit->getCategorie();

        // Module recommandé pour affichage (peut avoir déjà été assigné par fakeSubmit)
        $modulePropose = $this->scoring->trouverModuleRecommandePour($employe, $categorie);

        return $this->render('phishing/alerte.html.twig', [
            'resultat'      => $resultat,
            'gabarit'       => $gabarit,
            'employe'       => $employe,
            'indices'       => $gabarit->getIndicesPieges() ?? [],
            'token'         => $token,
            'modulePropose' => $modulePropose,
        ]);
    }

    // ══════════════════════════════════════════
    // SIGNAL — bouton "Signaler" dans l'email
    // Récompense : +10 vigilance
    // ══════════════════════════════════════════
    #[Route('/phishing/signal/{token}', name: 'phishing_signal')]
    public function signal(string $token, EntityManagerInterface $em): Response
    {
        $resultat = $em->getRepository(ResultatPhishing::class)
            ->findOneBy(['jetonTrackingUnique' => $token]);

        if (!$resultat) {
            return $this->render('phishing/erreur.html.twig');
        }

        $employe = $resultat->getEmploye();
        $already = false;

        if (!$resultat->isSignale() && !$resultat->isLienClique()) {
            $resultat->setSignale(true);
            $scoringResultat = $this->scoring->phishingSignalement($employe);
            $em->flush();
        } else {
            $already         = true;
            $scoringResultat = new ScoringResultat(
                nouveauScore:   $employe->getScoreVigilance(),
                nouveauxPoints: $employe->getTotalPoints(),
                deltaVigilance: 0,
                deltaPoints:    0,
                modulePropose:  null,
            );
        }

        return $this->render('phishing/felicitation.html.twig', [
            'employe'        => $employe,
            'deltaPoints'    => $scoringResultat->deltaPoints,
            'deltaVigilance' => $scoringResultat->deltaVigilance,
            'already'        => $already,
        ]);
    }


    private function assignerFormation(
        \App\Entity\Employe $employe,
        ModuleFormation $module,
        EntityManagerInterface $em
    ): void {
        $existante = $em->getRepository(ProgressionModule::class)->findOneBy([
            'employe' => $employe,
            'module'  => $module,
        ]);

        if (!$existante) {
            $prog = new ProgressionModule();
            $prog->setEmploye($employe)
                ->setModule($module)
                ->setStatut('NON_COMMENCE')
                ->setDateDernierAcces(new \DateTime());
            $em->persist($prog);
        }
    }
}