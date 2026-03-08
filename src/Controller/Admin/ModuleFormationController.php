<?php

namespace App\Controller\Admin;

use App\Entity\ModuleFormation;
use App\Entity\Categorie;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/modules')]
#[IsGranted('ROLE_ADMIN')]
class ModuleFormationController extends AbstractController
{
    #[Route('', name: 'admin_modules_liste')]
    public function liste(EntityManagerInterface $em): Response
    {
        $modules = $em->getRepository(ModuleFormation::class)->findBy([], ['dateCreation' => 'DESC']);
        $categories = $em->getRepository(Categorie::class)->findAll();
        
        return $this->render('admin/modules/liste.html.twig', [
            'modules' => $modules,
            'categories' => $categories,
        ]);
    }

    #[Route('/nouveau', name: 'admin_module_nouveau')]
    public function nouveau(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        $categories = $em->getRepository(Categorie::class)->findAll();

        if ($request->isMethod('POST')) {
            $errors = [];

            $titre = $request->request->get('titre');
            $description = $request->request->get('description');
            $categorieId = $request->request->get('categorie_id');
            $typeModule = $request->request->get('type_module');
            $difficulte = $request->request->get('difficulte');
            $dureeEstimee = $request->request->get('duree_estimee');
            $pointsReussite = $request->request->get('points_reussite');
            $etoilesReussite = $request->request->get('etoiles_reussite');
            $estPublie = $request->request->get('est_publie') ? true : false;

            // Validations
            if (empty($titre) || empty($description) || empty($categorieId)) {
                $errors[] = 'Tous les champs obligatoires doivent être remplis.';
            }

            $categorie = $em->getRepository(Categorie::class)->find($categorieId);
            if (!$categorie) {
                $errors[] = 'Catégorie invalide.';
            }

            if (empty($errors)) {
                $module = new ModuleFormation();
                $module->setTitre($titre);
                $module->setSlug($slugger->slug($titre)->lower());
                $module->setDescription($description);
                $module->setCategorie($categorie);
                $module->setTypeModule($typeModule ?? 'theorique');
                $module->setDifficulte($difficulte ?? 'debutant');
                $module->setDureeEstimee((int)($dureeEstimee ?? 30));
                $module->setPointsReussite((int)($pointsReussite ?? 100));
                $module->setEtoilesReussite((int)($etoilesReussite ?? 2));
                $module->setEstPublie($estPublie);

                $em->persist($module);
                $em->flush();

                $this->addFlash('success', '✅ Module créé avec succès !');
                return $this->redirectToRoute('admin_modules_liste');
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->render('admin/modules/nouveau.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/{id}/modifier', name: 'admin_module_modifier')]
    public function modifier(
        ModuleFormation $module,
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        $categories = $em->getRepository(Categorie::class)->findAll();

        if ($request->isMethod('POST')) {
            $errors = [];

            $titre = $request->request->get('titre');
            $description = $request->request->get('description');
            $categorieId = $request->request->get('categorie_id');
            $typeModule = $request->request->get('type_module');
            $difficulte = $request->request->get('difficulte');
            $dureeEstimee = $request->request->get('duree_estimee');
            $pointsReussite = $request->request->get('points_reussite');
            $etoilesReussite = $request->request->get('etoiles_reussite');
            $estPublie = $request->request->get('est_publie') ? true : false;

            if (empty($titre) || empty($description) || empty($categorieId)) {
                $errors[] = 'Tous les champs obligatoires doivent être remplis.';
            }

            $categorie = $em->getRepository(Categorie::class)->find($categorieId);
            if (!$categorie) {
                $errors[] = 'Catégorie invalide.';
            }

            if (empty($errors)) {
                $module->setTitre($titre);
                $module->setSlug($slugger->slug($titre)->lower());
                $module->setDescription($description);
                $module->setCategorie($categorie);
                $module->setTypeModule($typeModule);
                $module->setDifficulte($difficulte);
                $module->setDureeEstimee((int)$dureeEstimee);
                $module->setPointsReussite((int)$pointsReussite);
                $module->setEtoilesReussite((int)$etoilesReussite);
                $module->setEstPublie($estPublie);

                $em->flush();

                $this->addFlash('success', '✅ Module modifié avec succès !');
                return $this->redirectToRoute('admin_modules_liste');
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->render('admin/modules/modifier.html.twig', [
            'module' => $module,
            'categories' => $categories,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'admin_module_supprimer', methods: ['POST'])]
    public function supprimer(ModuleFormation $module, EntityManagerInterface $em): Response
    {
        $em->remove($module);
        $em->flush();

        $this->addFlash('success', '✅ Module supprimé avec succès !');
        return $this->redirectToRoute('admin_modules_liste');
    }

    #[Route('/{id}/toggle-publication', name: 'admin_module_toggle_publication', methods: ['POST'])]
    public function togglePublication(ModuleFormation $module, EntityManagerInterface $em): Response
    {
        $module->setEstPublie(!$module->isEstPublie());
        $em->flush();

        $status = $module->isEstPublie() ? 'publié' : 'dépublié';
        $this->addFlash('success', "✅ Module {$status} avec succès !");
        
        return $this->redirectToRoute('admin_modules_liste');
    }

    #[Route('/{id}', name: 'admin_module_details')]
    public function details(ModuleFormation $module): Response
    {
        return $this->render('admin/modules/details.html.twig', [
            'module' => $module,
        ]);
    }
}