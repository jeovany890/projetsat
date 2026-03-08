<?php

namespace App\Controller;

use App\Entity\CampagnePhishing;
use App\Entity\EnvoiPhishing;
use App\Entity\Employe;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PhishingTrackingController extends AbstractController
{
    #[Route('/track/email/{token}', name: 'phishing_track_email')]
    public function trackEmail(
        string $token,
        EntityManagerInterface $em,
        Request $request
    ): Response {
        // Trouver l'envoi par token
        $envoi = $em->getRepository(EnvoiPhishing::class)->findOneBy(['token' => $token]);

        if (!$envoi) {
            // Retourner un pixel transparent sans erreur
            return $this->createPixelResponse();
        }

        // Si l'email n'a jamais été ouvert, marquer comme ouvert
        if (!$envoi->getDateOuverture()) {
            $envoi->setDateOuverture(new \DateTime());
            $envoi->setAdresseIpOuverture($request->getClientIp());
            $envoi->setUserAgentOuverture($request->headers->get('User-Agent'));
            
            $em->flush();
        }

        // Retourner un pixel invisible (1x1 transparent)
        return $this->createPixelResponse();
    }

    #[Route('/track/click/{token}', name: 'phishing_track_click')]
    public function trackClick(
        string $token,
        EntityManagerInterface $em,
        Request $request
    ): Response {
        $envoi = $em->getRepository(EnvoiPhishing::class)->findOneBy(['token' => $token]);

        if (!$envoi) {
            return $this->render('phishing/erreur.html.twig', [
                'message' => 'Lien invalide ou expiré.'
            ]);
        }

        // Enregistrer le clic
        if (!$envoi->getDateClic()) {
            $envoi->setDateClic(new \DateTime());
            $envoi->setAdresseIpClic($request->getClientIp());
            $envoi->setUserAgentClic($request->headers->get('User-Agent'));
            
            // Marquer comme cliqué (piège tombé)
            $envoi->setAClique(true);
            
            // Ajuster le score de vigilance de l'employé
            $employe = $envoi->getEmploye();
            if ($employe) {
                $employe->ajusterScoreVigilance(-10); // -10 points pour avoir cliqué
            }
            
            $em->flush();
        }

        // Rediriger vers la page d'alerte/formation
        return $this->redirectToRoute('phishing_alerte', ['token' => $token]);
    }

    #[Route('/phishing-alerte/{token}', name: 'phishing_alerte')]
    public function alerte(
        string $token,
        EntityManagerInterface $em
    ): Response {
        $envoi = $em->getRepository(EnvoiPhishing::class)->findOneBy(['token' => $token]);

        if (!$envoi) {
            return $this->render('phishing/erreur.html.twig');
        }

        $gabarit = $envoi->getCampagne()->getGabarit();
        $employe = $envoi->getEmploye();

        return $this->render('phishing/alerte.html.twig', [
            'envoi' => $envoi,
            'gabarit' => $gabarit,
            'employe' => $employe,
        ]);
    }

    #[Route('/phishing/signaler/{token}', name: 'phishing_signaler', methods: ['POST'])]
    public function signaler(
        string $token,
        EntityManagerInterface $em
    ): Response {
        $envoi = $em->getRepository(EnvoiPhishing::class)->findOneBy(['token' => $token]);

        if (!$envoi) {
            return $this->json(['success' => false], 404);
        }

        // Marquer comme signalé
        if (!$envoi->getDateSignalement()) {
            $envoi->setDateSignalement(new \DateTime());
            $envoi->setASignale(true);
            
            // Récompenser l'employé pour avoir signalé
            $employe = $envoi->getEmploye();
            if ($employe && !$envoi->hasAClique()) {
                $employe->ajusterScoreVigilance(+15); // +15 points pour vigilance
                $employe->ajouterPoints(50); // 50 points bonus
                $employe->ajouterEtoiles(1); // 1 étoile
            }
            
            $em->flush();
        }

        return $this->json(['success' => true]);
    }

    private function createPixelResponse(): Response
    {
        // Créer une image GIF transparente 1x1 pixel
        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        
        $response = new Response($pixel);
        $response->headers->set('Content-Type', 'image/gif');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }
}