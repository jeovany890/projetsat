<?php

namespace App\Controller\Admin;

use App\Entity\Categorie;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/categories')]
#[IsGranted('ROLE_ADMIN')]
class CategorieController extends AbstractController
{
    #[Route('', name: 'admin_categories_liste')]
    public function liste(EntityManagerInterface $em): Response
    {
        $categories = $em->getRepository(Categorie::class)->findBy([], ['titre' => 'ASC']);
        
        return $this->render('admin/categories/liste.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/nouveau', name: 'admin_categorie_nouveau')]
    public function nouveau(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $errors = [];

            $titre = $request->request->get('titre');
            $description = $request->request->get('description');
            $statut = $request->request->get('statut', 'ACTIF'); // Par défaut ACTIF

            if (empty($titre)) {
                $errors[] = 'Le titre est obligatoire.';
            }

            $existante = $em->getRepository(Categorie::class)->findOneBy(['titre' => $titre]);
            if ($existante) {
                $errors[] = 'Une catégorie avec ce titre existe déjà.';
            }

            if (empty($errors)) {
                $categorie = new Categorie();
                $categorie->setTitre($titre);
                $categorie->setDescription($description);
                $categorie->setStatut($statut);
                $categorie->setDateCreation(new \DateTime());

                $em->persist($categorie);
                $em->flush();

                $this->addFlash('success', '✅ Catégorie créée avec succès !');
                return $this->redirectToRoute('admin_categories_liste');
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->render('admin/categories/nouveau.html.twig');
    }

    #[Route('/{id}/modifier', name: 'admin_categorie_modifier')]
    public function modifier(
        Categorie $categorie,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if ($request->isMethod('POST')) {
            $errors = [];

            $titre = $request->request->get('titre');
            $description = $request->request->get('description');
            $statut = $request->request->get('statut');

            if (empty($titre)) {
                $errors[] = 'Le titre est obligatoire.';
            }

            $existante = $em->getRepository(Categorie::class)->findOneBy(['titre' => $titre]);
            if ($existante && $existante->getId() !== $categorie->getId()) {
                $errors[] = 'Une autre catégorie avec ce titre existe déjà.';
            }

            if (empty($errors)) {
                $categorie->setTitre($titre);
                $categorie->setDescription($description);
                $categorie->setStatut($statut);

                $em->flush();

                $this->addFlash('success', '✅ Catégorie modifiée avec succès !');
                return $this->redirectToRoute('admin_categories_liste');
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->render('admin/categories/modifier.html.twig', [
            'categorie' => $categorie,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'admin_categorie_supprimer', methods: ['POST'])]
    public function supprimer(Categorie $categorie, EntityManagerInterface $em): Response
    {
        if ($categorie->getModules()->count() > 0) {
            $this->addFlash('error', '❌ Impossible de supprimer : cette catégorie contient ' . $categorie->getModules()->count() . ' module(s).');
            return $this->redirectToRoute('admin_categories_liste');
        }

        $em->remove($categorie);
        $em->flush();

        $this->addFlash('success', '✅ Catégorie supprimée !');
        return $this->redirectToRoute('admin_categories_liste');
    }

    #[Route('/{id}/toggle-activation', name: 'admin_categorie_toggle_activation', methods: ['POST'])]
    public function toggleActivation(Categorie $categorie, EntityManagerInterface $em): Response
    {
        $nouveauStatut = $categorie->getStatut() === 'ACTIF' ? 'INACTIF' : 'ACTIF';
        $categorie->setStatut($nouveauStatut);
        $em->flush();

        $message = $nouveauStatut === 'ACTIF' ? 'activée' : 'désactivée';
        $this->addFlash('success', "✅ Catégorie {$message} !");
        
        return $this->redirectToRoute('admin_categories_liste');
    }
}