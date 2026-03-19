<?php

namespace App\Controller;

use App\Entity\ResultatPhishing;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PhishingTrackingController extends AbstractController
{
    #[Route('/track/click/{token}', name: 'phishing_track_click')]
    public function trackClick(string $token, EntityManagerInterface $em, Request $request): Response
    {
        $resultat = $em->getRepository(ResultatPhishing::class)
            ->findOneBy(['jetonTrackingUnique' => $token]);

        if (!$resultat) {
            return $this->render('phishing/erreur.html.twig', ['message' => 'Lien invalide ou expiré.']);
        }

        // ✅ Si l'employé clique, il a FORCÉMENT ouvert le mail
        if (!$resultat->isEmailOuvert()) {
            $resultat->setEmailOuvert(true);
            $resultat->setDateOuverture(new \DateTime());
            $resultat->setAdresseIp($request->getClientIp());
            $resultat->setAgentUtilisateur($request->headers->get('User-Agent'));
            $resultat->getCampagne()?->incrementerEmailsOuverts();
        }

        if (!$resultat->isLienClique()) {
            $resultat->setLienClique(true);
            $resultat->setDateClic(new \DateTime());
            $resultat->getEmploye()?->ajusterScoreVigilance(-10);
            $resultat->getCampagne()?->incrementerLiensCliques();
        }

        $em->flush();

        return $this->redirectToRoute('phishing_formulaire', ['token' => $token]);
    }

    #[Route('/phishing-login/{token}', name: 'phishing_formulaire')]
    public function formulaire(string $token, Request $request, EntityManagerInterface $em): Response
    {
        $resultat = $em->getRepository(ResultatPhishing::class)
            ->findOneBy(['jetonTrackingUnique' => $token]);

        if (!$resultat) {
            return $this->render('phishing/erreur.html.twig');
        }

        if ($request->isMethod('POST')) {
            // ✅ S'il soumet des données, il a aussi ouvert + cliqué
            if (!$resultat->isEmailOuvert()) {
                $resultat->setEmailOuvert(true);
                $resultat->setDateOuverture(new \DateTime());
                $resultat->getCampagne()?->incrementerEmailsOuverts();
            }
            if (!$resultat->isLienClique()) {
                $resultat->setLienClique(true);
                $resultat->setDateClic(new \DateTime());
                $resultat->getCampagne()?->incrementerLiensCliques();
            }
            if (!$resultat->isDonneesSubmises()) {
                $resultat->setDonneesSubmises(true);
                $resultat->setDateSubmission(new \DateTime());
                $resultat->getEmploye()?->ajusterScoreVigilance(-25); // -10 clic + -15 données
                $resultat->getCampagne()?->incrementerDonneesSubmises();
            }
            $em->flush();
            return $this->redirectToRoute('phishing_alerte', ['token' => $token]);
        }

        return $this->render('phishing/formulaire.html.twig', [
            'token'   => $token,
            'gabarit' => $resultat->getCampagne()->getGabarit(),
            'employe' => $resultat->getEmploye(),
        ]);
    }

    #[Route('/phishing-alerte/{token}', name: 'phishing_alerte')]
    public function alerte(string $token, EntityManagerInterface $em): Response
    {
        $resultat = $em->getRepository(ResultatPhishing::class)
            ->findOneBy(['jetonTrackingUnique' => $token]);

        if (!$resultat) {
            return $this->render('phishing/erreur.html.twig');
        }

        return $this->render('phishing/alerte.html.twig', [
            'resultat' => $resultat,
            'gabarit'  => $resultat->getCampagne()->getGabarit(),
            'employe'  => $resultat->getEmploye(),
        ]);
    }

    #[Route('/phishing/signaler/{token}', name: 'phishing_signaler', methods: ['POST'])]
    public function signaler(string $token, EntityManagerInterface $em): Response
    {
        $resultat = $em->getRepository(ResultatPhishing::class)
            ->findOneBy(['jetonTrackingUnique' => $token]);

        if (!$resultat) {
            return $this->json(['success' => false], 404);
        }

        if (!$resultat->isEmailSignale()) {
            $resultat->setEmailSignale(true);
            $resultat->setDateSignalement(new \DateTime());

            $employe = $resultat->getEmploye();
            if ($employe && !$resultat->isLienClique()) {
                $employe->ajusterScoreVigilance(+15);
                $employe->ajouterPoints(50);
                $employe->ajouterEtoiles(1);
            }

            $resultat->getCampagne()?->incrementerEmailsSignales();
            $em->flush();
        }

        return $this->json(['success' => true]);
    }

}