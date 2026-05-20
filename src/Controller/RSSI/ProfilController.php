<?php

namespace App\Controller\RSSI;

use App\Entity\RSSI;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rssi/profil')]
#[IsGranted('ROLE_RSSI')]
class ProfilController extends AbstractController
{
    #[Route('', name: 'rssi_profil', methods: ['GET'])]
    public function index(): Response
    {
        /** @var RSSI $rssi */
        $rssi = $this->getUser();

        return $this->render('rssi/profil.html.twig', [
            'rssi' => $rssi,
            'edit_mode' => false
        ]);
    }

    #[Route('/modifier', name: 'rssi_profil_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        /** @var RSSI $rssi */
        $rssi = $this->getUser();

        if ($request->isMethod('POST')) {
            $prenom    = trim($request->request->get('prenom', ''));
            $nom       = trim($request->request->get('nom', ''));
            $telephone = trim($request->request->get('telephone', ''));
            $mdpActuel = $request->request->get('mot_de_passe_actuel', '');
            $mdpNouv   = $request->request->get('nouveau_mot_de_passe', '');
            $mdpConf   = $request->request->get('confirmation_mot_de_passe', '');

            if (!$prenom || !$nom) {
                $this->addFlash('error', 'Les champs prénom et nom sont obligatoires.');
                return $this->redirectToRoute('rssi_profil_edit');
            }

            $rssi->setPrenom($prenom)
                 ->setNom($nom)
                 ->setTelephone($telephone ?: null);

            if ($mdpNouv) {
                if (!$hasher->isPasswordValid($rssi, $mdpActuel)) {
                    $this->addFlash('error', 'Mot de passe actuel incorrect.');
                    return $this->redirectToRoute('rssi_profil_edit');
                }
                if ($mdpNouv !== $mdpConf) {
                    $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
                    return $this->redirectToRoute('rssi_profil_edit');
                }
                if (strlen($mdpNouv) < 8) {
                    $this->addFlash('error', 'Le nouveau mot de passe doit contenir au moins 8 caractères.');
                    return $this->redirectToRoute('rssi_profil_edit');
                }
                $rssi->setMotDePasse($hasher->hashPassword($rssi, $mdpNouv));
            }

            $em->flush();
            $this->addFlash('success', 'Profil RSSI mis à jour avec succès.');
            return $this->redirectToRoute('rssi_profil');
        }

        return $this->render('rssi/profil.html.twig', [
            'rssi' => $rssi,
            'edit_mode' => true
        ]);
    }
}
