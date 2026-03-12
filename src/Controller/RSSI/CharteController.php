<?php

namespace App\Controller\RSSI;

use App\Entity\RSSI;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rssi/charte')]
#[IsGranted('ROLE_RSSI')]
class CharteController extends AbstractController
{
    #[Route('', name: 'rssi_charte')]
    public function afficher(Request $request, EntityManagerInterface $em): Response
    {
        /** @var RSSI $rssi */
        $rssi       = $this->getUser();
        $entreprise = $rssi->getEntreprise();

        // Si pas d'entreprise liée, rediriger vers dashboard avec message
        if (!$entreprise) {
            $this->addFlash('error', 'Aucune entreprise liée à votre compte. Contactez l\'administrateur.');
            return $this->redirectToRoute('rssi_dashboard');
        }

        // Si charte déjà acceptée, rediriger vers dashboard
        if ($entreprise->isCharteAcceptee()) {
            return $this->redirectToRoute('rssi_dashboard');
        }

        if ($request->isMethod('POST')) {
            $confirmation = $request->request->get('acceptation_charte');
            $nomSignataire = trim($request->request->get('nom_signataire', ''));
            $fonctionSignataire = trim($request->request->get('fonction_signataire', ''));

            if ($confirmation !== 'OUI_JACCEPTE') {
                $this->addFlash('error', 'Vous devez saisir exactement "OUI_JACCEPTE" pour confirmer votre acceptation.');
                return $this->render('rssi/charte/index.html.twig', [
                    'entreprise'        => $entreprise,
                    'rssi'              => $rssi,
                    'nomSignataire'     => $nomSignataire,
                    'fonctionSignataire'=> $fonctionSignataire,
                ]);
            }

            if (empty($nomSignataire) || empty($fonctionSignataire)) {
                $this->addFlash('error', 'Le nom complet et la fonction du signataire sont obligatoires.');
                return $this->render('rssi/charte/index.html.twig', [
                    'entreprise'        => $entreprise,
                    'rssi'              => $rssi,
                    'nomSignataire'     => $nomSignataire,
                    'fonctionSignataire'=> $fonctionSignataire,
                ]);
            }

            // Accepter la charte
            $entreprise->accepterCharte();
            $em->flush();

            $this->addFlash('success', 'Charte d\'utilisation acceptée. Bienvenue sur SAT Platform !');
            return $this->redirectToRoute('rssi_dashboard');
        }

        return $this->render('rssi/charte/index.html.twig', [
            'entreprise'        => $entreprise,
            'rssi'              => $rssi,
            'nomSignataire'     => $rssi->getNomComplet(),
            'fonctionSignataire'=> 'Responsable Sécurité des Systèmes d\'Information',
        ]);
    }
}