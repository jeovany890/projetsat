<?php

namespace App\Controller;

use App\Entity\ResultatPhishing;
use App\Entity\CampagnePhishing;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PhishingTrackingController extends AbstractController
{
    // ================================================================
    // TRACK OUVERTURE EMAIL (pixel 1x1)
    // ================================================================
    #[Route('/track/email/{token}', name: 'phishing_track_email')]
    public function trackEmail(
        string $token,
        EntityManagerInterface $em,
        Request $request
    ): Response {
        $resultat = $em->getRepository(ResultatPhishing::class)
            ->findOneBy(['jetonTrackingUnique' => $token]);

        if ($resultat && !$resultat->isEmailOuvert()) {
            $resultat->setEmailOuvert(true);
            $resultat->setDateOuverture(new \DateTime());
            $resultat->setAdresseIp($request->getClientIp());
            $resultat->setAgentUtilisateur($request->headers->get('User-Agent'));

            // Incrémenter compteur campagne
            $resultat->getCampagne()->incrementerEmailsOuverts();

            $em->flush();
        }

        return $this->pixelResponse();
    }

    // ================================================================
    // TRACK CLIC SUR LIEN PIÉGÉ
    // ================================================================
    #[Route('/track/click/{token}', name: 'phishing_track_click')]
    public function trackClick(
        string $token,
        EntityManagerInterface $em,
        Request $request
    ): Response {
        $resultat = $em->getRepository(ResultatPhishing::class)
            ->findOneBy(['jetonTrackingUnique' => $token]);

        if (!$resultat) {
            return $this->redirectToRoute('app_login');
        }

        if (!$resultat->isLienClique()) {
            $resultat->setLienClique(true);
            $resultat->setDateClic(new \DateTime());
            $resultat->setAdresseIp($request->getClientIp());
            $resultat->setAgentUtilisateur($request->headers->get('User-Agent'));

            // Incrémenter compteur campagne
            $resultat->getCampagne()->incrementerLiensCliques();

            // Pénaliser le score de vigilance de l'employé (-10)
            $employe = $resultat->getEmploye();
            if ($employe) {
                $employe->ajusterScoreVigilance(-10);
            }

            $em->flush();
        }

        // Rediriger vers le faux formulaire de login
        return $this->redirectToRoute('phishing_formulaire', ['token' => $token]);
    }

    // ================================================================
    // FAUX FORMULAIRE DE LOGIN
    // ================================================================
    #[Route('/phishing-login/{token}', name: 'phishing_formulaire')]
    public function formulaire(
        string $token,
        EntityManagerInterface $em
    ): Response {
        $resultat = $em->getRepository(ResultatPhishing::class)
            ->findOneBy(['jetonTrackingUnique' => $token]);

        if (!$resultat) {
            return $this->render('phishing/erreur.html.twig');
        }

        return $this->render('phishing/formulaire.html.twig', [
            'resultat' => $resultat,
            'gabarit'  => $resultat->getCampagne()->getGabarit(),
            'employe'  => $resultat->getEmploye(),
            'token'    => $token,
        ]);
    }

    // ================================================================
    // SOUMISSION DU FAUX FORMULAIRE
    // ================================================================
    #[Route('/phishing-login/{token}/soumettre', name: 'phishing_soumettre', methods: ['POST'])]
    public function soumettre(
        string $token,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $resultat = $em->getRepository(ResultatPhishing::class)
            ->findOneBy(['jetonTrackingUnique' => $token]);

        if (!$resultat) {
            return $this->render('phishing/erreur.html.twig');
        }

        // Collecter les noms des champs saisis (pas les valeurs réelles — éthique)
        $champsSubmis = [];
        foreach ($request->request->all() as $champ => $valeur) {
            if ($champ === '_token') continue;
            $champsSubmis[$champ] = !empty($valeur) ? 'renseigné' : 'vide';
        }

        // Gravité selon le type de champ saisi
        $gravite = 'FAIBLE';
        $noms = array_keys($champsSubmis);
        if (array_intersect($noms, ['password', 'mot_de_passe', 'pin', 'code_secret'])) {
            $gravite = 'CRITIQUE';
        } elseif (array_intersect($noms, ['email', 'username', 'identifiant', 'telephone'])) {
            $gravite = 'ELEVE';
        } elseif (count($champsSubmis) > 0) {
            $gravite = 'MOYEN';
        }

        if (!$resultat->isDonneesSubmises()) {
            $resultat->setDonneesSubmises(true);
            $resultat->setDateSubmission(new \DateTime());

            $details = new \App\Entity\DonneesSubmisesPhishing();
            $details->setChampsSubmis($champsSubmis)
                ->setNombreChamps(count($champsSubmis))
                ->setGravite($gravite)
                ->setAdresseIp($request->getClientIp() ?? '0.0.0.0')
                ->setAgentUtilisateur($request->headers->get('User-Agent') ?? '')
                ->setResultatPhishing($resultat);
            $em->persist($details);

            $resultat->getCampagne()->incrementerDonneesSubmises();

            // Pénalité supplémentaire -15 (en plus des -10 du clic)
            $employe = $resultat->getEmploye();
            if ($employe) {
                $employe->ajusterScoreVigilance(-15);
            }

            $em->flush();
        }

        return $this->redirectToRoute('phishing_alerte', ['token' => $token]);
    }

    // ================================================================
    // PAGE D'ALERTE (après formulaire ou directement après clic)
    // ================================================================
    #[Route('/phishing-alerte/{token}', name: 'phishing_alerte')]
    public function alerte(
        string $token,
        EntityManagerInterface $em
    ): Response {
        $resultat = $em->getRepository(ResultatPhishing::class)
            ->findOneBy(['jetonTrackingUnique' => $token]);

        if (!$resultat) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('phishing/alerte.html.twig', [
            'resultat' => $resultat,
            'gabarit'  => $resultat->getCampagne()->getGabarit(),
            'employe'  => $resultat->getEmploye(),
        ]);
    }

    // ================================================================
    // SIGNALER UN EMAIL PHISHING (l'employé a bien détecté)
    // ================================================================
    #[Route('/phishing/signaler/{token}', name: 'phishing_signaler', methods: ['POST'])]
    public function signaler(
        string $token,
        EntityManagerInterface $em
    ): Response {
        $resultat = $em->getRepository(ResultatPhishing::class)
            ->findOneBy(['jetonTrackingUnique' => $token]);

        if (!$resultat) {
            return $this->json(['success' => false, 'message' => 'Token invalide.'], 404);
        }

        if (!$resultat->isEmailSignale()) {
            $resultat->setEmailSignale(true);
            $resultat->setDateSignalement(new \DateTime());

            // Incrémenter compteur campagne
            $resultat->getCampagne()->incrementerEmailsSignales();

            // Récompenser l'employé uniquement s'il n'a pas cliqué
            $employe = $resultat->getEmploye();
            if ($employe && !$resultat->isLienClique()) {
                $employe->ajusterScoreVigilance(+15);
                $employe->ajouterPoints(50);
                $employe->ajouterEtoiles(1);
            }

            $em->flush();
        }

        return $this->json(['success' => true]);
    }

    // ================================================================
    // HELPER : réponse pixel GIF 1x1 transparent
    // ================================================================
    private function pixelResponse(): Response
    {
        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        $response = new Response($pixel);
        $response->headers->set('Content-Type', 'image/gif');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        return $response;
    }
}