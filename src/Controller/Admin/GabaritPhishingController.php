<?php

namespace App\Controller\Admin;

use App\Entity\GabaritPhishing;
use App\Entity\Administrateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/gabarits-phishing')]
#[IsGranted('ROLE_ADMIN')]
class GabaritPhishingController extends AbstractController
{
    #[Route('', name: 'admin_gabarits_liste')]
    public function liste(EntityManagerInterface $em): Response
    {
        $gabarits = $em->getRepository(GabaritPhishing::class)->findBy([], ['dateCreation' => 'DESC']);
        
        return $this->render('admin/gabarits/liste.html.twig', [
            'gabarits' => $gabarits,
        ]);
    }

    #[Route('/nouveau', name: 'admin_gabarit_nouveau')]
    public function nouveau(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        if ($request->isMethod('POST')) {
            $errors = [];

            $titre = $request->request->get('titre');
            $description = $request->request->get('description');
            $categorie = $request->request->get('categorie');
            $difficulte = $request->request->get('difficulte');
            $compteEmailDsn = $request->request->get('compte_email_dsn');
            $nomExpediteur = $request->request->get('nom_expediteur');
            $emailExpediteur = $request->request->get('email_expediteur');
            $sujetEmail = $request->request->get('sujet_email');
            $contenuHtml = $request->request->get('contenu_html');
            $contenuTexte = $request->request->get('contenu_texte');
            $indicesPieges = $request->request->get('indices_pieges');
            $estActif = $request->request->get('est_actif') ? true : false;

            // Validations
            if (empty($titre) || empty($categorie) || empty($sujetEmail) || empty($contenuHtml)) {
                $errors[] = 'Tous les champs obligatoires doivent être remplis.';
            }

            if (empty($errors)) {
                $gabarit = new GabaritPhishing();
                $gabarit->setTitre($titre);
                $gabarit->setSlug($slugger->slug($titre)->lower());
                $gabarit->setDescription($description);
                $gabarit->setCategorie($categorie);
                $gabarit->setDifficulte($difficulte ?? 'moyen');
                $gabarit->setCompteEmailDsn($compteEmailDsn);
                $gabarit->setNomExpediteur($nomExpediteur);
                $gabarit->setEmailExpediteur($emailExpediteur);
                $gabarit->setSujetEmail($sujetEmail);
                $gabarit->setContenuHtml($contenuHtml);
                $gabarit->setContenuTexte($contenuTexte);
                
                // Convertir les indices en array JSON
                if ($indicesPieges) {
                    $indices = array_map('trim', explode("\n", $indicesPieges));
                    $indices = array_filter($indices);
                    $gabarit->setIndicesPieges($indices);
                }
                
                $gabarit->setEstActif($estActif);
                
                // Associer à l'admin connecté
                $admin = $this->getUser();
                if ($admin instanceof Administrateur) {
                    $gabarit->setAdministrateur($admin);
                }

                $em->persist($gabarit);
                $em->flush();

                $this->addFlash('success', '✅ Gabarit phishing créé avec succès !');
                return $this->redirectToRoute('admin_gabarits_liste');
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->render('admin/gabarits/nouveau.html.twig');
    }

    #[Route('/{id}/modifier', name: 'admin_gabarit_modifier')]
    public function modifier(
        GabaritPhishing $gabarit,
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        if ($request->isMethod('POST')) {
            $errors = [];

            $titre = $request->request->get('titre');
            $description = $request->request->get('description');
            $categorie = $request->request->get('categorie');
            $difficulte = $request->request->get('difficulte');
            $compteEmailDsn = $request->request->get('compte_email_dsn');
            $nomExpediteur = $request->request->get('nom_expediteur');
            $emailExpediteur = $request->request->get('email_expediteur');
            $sujetEmail = $request->request->get('sujet_email');
            $contenuHtml = $request->request->get('contenu_html');
            $contenuTexte = $request->request->get('contenu_texte');
            $indicesPieges = $request->request->get('indices_pieges');
            $estActif = $request->request->get('est_actif') ? true : false;

            if (empty($titre) || empty($categorie) || empty($sujetEmail) || empty($contenuHtml)) {
                $errors[] = 'Tous les champs obligatoires doivent être remplis.';
            }

            if (empty($errors)) {
                $gabarit->setTitre($titre);
                $gabarit->setSlug($slugger->slug($titre)->lower());
                $gabarit->setDescription($description);
                $gabarit->setCategorie($categorie);
                $gabarit->setDifficulte($difficulte);
                $gabarit->setCompteEmailDsn($compteEmailDsn);
                $gabarit->setNomExpediteur($nomExpediteur);
                $gabarit->setEmailExpediteur($emailExpediteur);
                $gabarit->setSujetEmail($sujetEmail);
                $gabarit->setContenuHtml($contenuHtml);
                $gabarit->setContenuTexte($contenuTexte);
                
                if ($indicesPieges) {
                    $indices = array_map('trim', explode("\n", $indicesPieges));
                    $indices = array_filter($indices);
                    $gabarit->setIndicesPieges($indices);
                }
                
                $gabarit->setEstActif($estActif);

                $em->flush();

                $this->addFlash('success', '✅ Gabarit modifié avec succès !');
                return $this->redirectToRoute('admin_gabarits_liste');
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->render('admin/gabarits/modifier.html.twig', [
            'gabarit' => $gabarit,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'admin_gabarit_supprimer', methods: ['POST'])]
    public function supprimer(GabaritPhishing $gabarit, EntityManagerInterface $em): Response
    {
        $em->remove($gabarit);
        $em->flush();

        $this->addFlash('success', '✅ Gabarit supprimé avec succès !');
        return $this->redirectToRoute('admin_gabarits_liste');
    }

    #[Route('/{id}/toggle-activation', name: 'admin_gabarit_toggle_activation', methods: ['POST'])]
    public function toggleActivation(GabaritPhishing $gabarit, EntityManagerInterface $em): Response
    {
        $gabarit->setEstActif(!$gabarit->isEstActif());
        $em->flush();

        $status = $gabarit->isEstActif() ? 'activé' : 'désactivé';
        $this->addFlash('success', "✅ Gabarit {$status} avec succès !");
        
        return $this->redirectToRoute('admin_gabarits_liste');
    }

    #[Route('/{id}/apercu', name: 'admin_gabarit_apercu')]
    public function apercu(GabaritPhishing $gabarit): Response
    {
        return $this->render('admin/gabarits/apercu.html.twig', [
            'gabarit' => $gabarit,
        ]);
    }
}