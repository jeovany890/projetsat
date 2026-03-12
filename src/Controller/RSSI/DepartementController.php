<?php

namespace App\Controller\RSSI;

use App\Entity\Departement;
use App\Entity\RSSI;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rssi/departements')]
#[IsGranted('ROLE_RSSI')]
class DepartementController extends AbstractController
{
    private function getEntreprise(): \App\Entity\Entreprise
    {
        /** @var RSSI $rssi */
        $rssi = $this->getUser();
        return $rssi->getEntreprise();
    }

    #[Route('', name: 'rssi_departements_liste')]
    public function liste(EntityManagerInterface $em): Response
    {
        $departements = $em->getRepository(Departement::class)
            ->findBy(['entreprise' => $this->getEntreprise()], ['nom' => 'ASC']);

        return $this->render('rssi/departements/liste.html.twig', [
            'departements' => $departements,
        ]);
    }

    #[Route('/nouveau', name: 'rssi_departements_nouveau', methods: ['GET', 'POST'])]
    public function nouveau(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $nom         = trim($request->request->get('nom', ''));
            $description = trim($request->request->get('description', ''));

            if (empty($nom)) {
                $this->addFlash('error', 'Le nom du département est obligatoire.');
                return $this->redirectToRoute('rssi_departements_nouveau');
            }

            $departement = new Departement();
            $departement->setNom($nom);
            $departement->setDescription($description ?: null);
            $departement->setEntreprise($this->getEntreprise());

            $em->persist($departement);
            $em->flush();

            $this->addFlash('success', "Département « {$nom} » créé avec succès.");
            return $this->redirectToRoute('rssi_departements_liste');
        }

        return $this->render('rssi/departements/nouveau.html.twig');
    }

    #[Route('/{id}/modifier', name: 'rssi_departements_modifier', methods: ['GET', 'POST'])]
    public function modifier(Departement $departement, Request $request, EntityManagerInterface $em): Response
    {
        // Sécurité : vérifier que le département appartient à l'entreprise du RSSI
        if ($departement->getEntreprise() !== $this->getEntreprise()) {
            throw $this->createAccessDeniedException();
        }

        if ($request->isMethod('POST')) {
            $nom         = trim($request->request->get('nom', ''));
            $description = trim($request->request->get('description', ''));

            if (empty($nom)) {
                $this->addFlash('error', 'Le nom est obligatoire.');
                return $this->redirectToRoute('rssi_departements_modifier', ['id' => $departement->getId()]);
            }

            $departement->setNom($nom);
            $departement->setDescription($description ?: null);
            $em->flush();

            $this->addFlash('success', 'Département modifié avec succès.');
            return $this->redirectToRoute('rssi_departements_liste');
        }

        return $this->render('rssi/departements/modifier.html.twig', [
            'departement' => $departement,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'rssi_departements_supprimer', methods: ['POST'])]
    public function supprimer(Departement $departement, EntityManagerInterface $em): Response
    {
        if ($departement->getEntreprise() !== $this->getEntreprise()) {
            throw $this->createAccessDeniedException();
        }

        if ($departement->compterEmployes() > 0) {
            $this->addFlash('error', 'Impossible de supprimer un département contenant des employés.');
            return $this->redirectToRoute('rssi_departements_liste');
        }

        $nom = $departement->getNom();
        $em->remove($departement);
        $em->flush();

        $this->addFlash('success', "Département « {$nom} » supprimé.");
        return $this->redirectToRoute('rssi_departements_liste');
    }
}