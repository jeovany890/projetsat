<?php

namespace App\Controller\Admin;

use App\Entity\CampagnePhishing;
use App\Entity\GabaritPhishing;
use App\Entity\Administrateur;
use App\Entity\ModuleFormation;
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

    // ══════════════════════════════════════════
    // Récupère les catégories distinctes des
    // modules de formation publiés.
    // Ces catégories servent à alimenter le
    // select "Catégorie" du gabarit phishing.
    // Ainsi gabarit.categorie == module.categorie
    // → liaison automatique dans ScoringMoteurService
    // ══════════════════════════════════════════
    private function getCategoriesModules(EntityManagerInterface $em): array
    {
        $modules = $em->getRepository(ModuleFormation::class)->findBy(
            ['estPublie' => true],
            ['categorie' => 'ASC']
        );

        $categories = [];
        foreach ($modules as $module) {
            $cat = $module->getCategorie();
            if ($cat && !in_array($cat, array_column($categories, 'valeur'))) {
                $categories[] = [
                    'valeur' => $cat,
                    'label'  => ucwords(str_replace('_', ' ', $cat)),
                ];
            }
        }

        return $categories;
    }

    #[Route('/nouveau', name: 'admin_gabarit_nouveau')]
    public function nouveau(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        if ($request->isMethod('POST')) {
            $errors = [];

            $titre           = $request->request->get('titre');
            $description     = $request->request->get('description');
            $categorie       = $request->request->get('categorie');
            $difficulte      = $request->request->get('difficulte', 'moyen');
            $nomExpediteur   = $request->request->get('nom_expediteur');
            $emailExpediteur = $request->request->get('email_expediteur');
            $sujetEmail      = $request->request->get('sujet_email');
            $contenuHtml     = $request->request->get('contenu_html');
            $compteEmailDsn  = $request->request->get('compte_email_dsn');
            $indicesPieges   = $request->request->get('indices_pieges');
            $estActif        = $request->request->get('est_actif') ? true : false;

            if (empty($titre) || empty($categorie)) {
                $errors[] = 'Le titre et la catégorie sont obligatoires.';
            }
            if (empty($contenuHtml)) {
                $errors[] = 'Le contenu HTML est obligatoire.';
            }

            if (empty($errors)) {
                $gabarit = new GabaritPhishing();
                $gabarit->setTitre($titre)
                    ->setSlug($slugger->slug($titre)->lower())
                    ->setDescription($description)
                    ->setCategorie($categorie)
                    ->setDifficulte($difficulte)
                    ->setCompteEmailDsn($compteEmailDsn ?: null)
                    ->setNomExpediteur($nomExpediteur)
                    ->setEmailExpediteur($emailExpediteur)
                    ->setSujetEmail($sujetEmail)
                    ->setContenuHtml($contenuHtml ?? '')
                    ->setEstActif($estActif);

                if ($indicesPieges) {
                    $indices = array_filter(array_map('trim', explode("\n", $indicesPieges)));
                    $gabarit->setIndicesPieges(array_values($indices));
                }

                $admin = $this->getUser();
                if ($admin instanceof Administrateur) {
                    $gabarit->setAdministrateur($admin);
                }

                $em->persist($gabarit);
                $em->flush();

                $this->addFlash('success', ' Gabarit créé avec succès !');
                return $this->redirectToRoute('admin_gabarits_liste');
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->render('admin/gabarits/nouveau.html.twig', [
            // Catégories des modules publiés → select dynamique
            'categoriesModules' => $this->getCategoriesModules($em),
        ]);
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

            $titre           = $request->request->get('titre');
            $description     = $request->request->get('description');
            $categorie       = $request->request->get('categorie');
            $difficulte      = $request->request->get('difficulte', 'moyen');
            $nomExpediteur   = $request->request->get('nom_expediteur');
            $emailExpediteur = $request->request->get('email_expediteur');
            $sujetEmail      = $request->request->get('sujet_email');
            $contenuHtml     = $request->request->get('contenu_html');
            $compteEmailDsn  = $request->request->get('compte_email_dsn');
            $indicesPieges   = $request->request->get('indices_pieges');
            $estActif        = $request->request->get('est_actif') ? true : false;

            if (empty($titre) || empty($categorie)) {
                $errors[] = 'Le titre et la catégorie sont obligatoires.';
            }
            if (empty($contenuHtml)) {
                $errors[] = 'Le contenu HTML est obligatoire.';
            }

            if (empty($errors)) {
                $gabarit->setTitre($titre)
                    ->setSlug($slugger->slug($titre)->lower())
                    ->setDescription($description)
                    ->setCategorie($categorie)
                    ->setDifficulte($difficulte)
                    ->setCompteEmailDsn($compteEmailDsn ?: null)
                    ->setNomExpediteur($nomExpediteur)
                    ->setEmailExpediteur($emailExpediteur)
                    ->setSujetEmail($sujetEmail)
                    ->setContenuHtml($contenuHtml ?? '')
                    ->setEstActif($estActif);

                if ($indicesPieges) {
                    $indices = array_filter(array_map('trim', explode("\n", $indicesPieges)));
                    $gabarit->setIndicesPieges(array_values($indices));
                }

                $em->flush();

                $this->addFlash('success', ' Gabarit modifié avec succès !');
                return $this->redirectToRoute('admin_gabarits_liste');
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->render('admin/gabarits/modifier.html.twig', [
            'gabarit'           => $gabarit,
            // Catégories des modules publiés → select dynamique
            'categoriesModules' => $this->getCategoriesModules($em),
        ]);
    }

    #[Route('/{id}/supprimer', name: 'admin_gabarit_supprimer', methods: ['POST'])]
    public function supprimer(GabaritPhishing $gabarit, EntityManagerInterface $em): Response
    {
        $campagnes = $em->getRepository(CampagnePhishing::class)->findBy(['gabarit' => $gabarit]);
        if (count($campagnes) > 0) {
            $this->addFlash('danger', 'Impossible de supprimer ce gabarit car il est utilisé par ' . count($campagnes) . ' campagne(s). Supprimez d\'abord les campagnes concernées.');
            return $this->redirectToRoute('admin_gabarits_liste');
        }

        $em->remove($gabarit);
        $em->flush();
        $this->addFlash('success', 'Gabarit supprimé !');
        return $this->redirectToRoute('admin_gabarits_liste');
    }

    #[Route('/{id}/toggle-activation', name: 'admin_gabarit_toggle_activation', methods: ['POST'])]
    public function toggleActivation(GabaritPhishing $gabarit, EntityManagerInterface $em): Response
    {
        $gabarit->setEstActif(!$gabarit->isEstActif());
        $em->flush();

        $status = $gabarit->isEstActif() ? 'activé' : 'désactivé';
        $this->addFlash('success', "Gabarit {$status} !");
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