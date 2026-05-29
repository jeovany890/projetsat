<?php

namespace App\Controller\Admin;

use App\Entity\ModuleFormation;
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
        return $this->render('admin/modules/liste.html.twig', ['modules' => $modules]);
    }

    #[Route('/nouveau', name: 'admin_module_nouveau', methods: ['GET', 'POST'])]
    public function nouveau(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        if ($request->isMethod('POST')) {
            $titre = $request->request->get('titre');
            $description = $request->request->get('description');
            $categorie = $request->request->get('categorie');

            if (!$titre || !$description || !$categorie) {
                $this->addFlash('error', 'Tous les champs obligatoires doivent être remplis.');
                return $this->redirectToRoute('admin_module_nouveau');
            }

            // Simulation obligatoire
            $simulationTitre = $request->request->get('simulation_titre');
            $simulationType = $request->request->get('simulation_type');
            $simulationJson = $request->request->get('simulation_contenu_json');
            if (empty($simulationTitre) || empty($simulationType) || empty($simulationJson)) {
                $this->addFlash('error', 'La simulation est obligatoire. Veuillez remplir le titre, le type et au moins un contenu.');
                return $this->redirectToRoute('admin_module_nouveau');
            }
            $simulationData = json_decode($simulationJson, true);
            if (!is_array($simulationData) || empty($simulationData)) {
                $this->addFlash('error', 'Le contenu de la simulation est invalide.');
                return $this->redirectToRoute('admin_module_nouveau');
            }

            $module = new ModuleFormation();
            $module->setTitre($titre);
            $module->setSlug($slugger->slug($titre)->lower());
            $module->setDescription($description);
            $module->setCategorie($categorie);
            $module->setTypeModule($request->request->get('type_module', 'formation'));
            $module->setDifficulte($request->request->get('difficulte', 'debutant'));
            $module->setDureeEstimee((int)$request->request->get('duree_estimee', 30));
            $module->setPointsReussite((int)$request->request->get('points_reussite', 100));
            $module->setEstPublie(false); // Jamais publié à la création

            // Simulation
            $module->setSimulationTitre($simulationTitre);
            $module->setSimulationType($simulationType);
            $module->setSimulationContenu($simulationData);

            $em->persist($module);
            $em->flush();

            $this->addFlash('success', '✅ Module créé avec succès ! Vous pouvez maintenant ajouter des chapitres.');
            return $this->redirectToRoute('admin_module_details', ['id' => $module->getId()]);
        }

        return $this->render('admin/modules/nouveau.html.twig');
    }

    #[Route('/{id}/modifier', name: 'admin_module_modifier', methods: ['GET', 'POST'])]
    public function modifier(ModuleFormation $module, Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        if ($request->isMethod('POST')) {
            $titre = $request->request->get('titre');
            $description = $request->request->get('description');
            $categorie = $request->request->get('categorie');

            if (!$titre || !$description || !$categorie) {
                $this->addFlash('error', 'Tous les champs obligatoires doivent être remplis.');
                return $this->redirectToRoute('admin_module_modifier', ['id' => $module->getId()]);
            }

            $module->setTitre($titre);
            $module->setSlug($slugger->slug($titre)->lower());
            $module->setDescription($description);
            $module->setCategorie($categorie);
            $module->setDifficulte($request->request->get('difficulte'));
            $module->setDureeEstimee((int)$request->request->get('duree_estimee'));
            $module->setPointsReussite((int)$request->request->get('points_reussite'));

            // Mise à jour de la simulation (obligatoire)
            $simulationTitre = $request->request->get('simulation_titre');
            $simulationType = $request->request->get('simulation_type');
            $simulationJson = $request->request->get('simulation_contenu_json');
            if (!empty($simulationTitre) && !empty($simulationType) && !empty($simulationJson)) {
                $simulationData = json_decode($simulationJson, true);
                if (is_array($simulationData) && !empty($simulationData)) {
                    $module->setSimulationTitre($simulationTitre);
                    $module->setSimulationType($simulationType);
                    $module->setSimulationContenu($simulationData);
                } else {
                    $this->addFlash('error', 'Le contenu de la simulation est invalide.');
                    return $this->redirectToRoute('admin_module_modifier', ['id' => $module->getId()]);
                }
            } elseif ($module->getSimulationTitre() === null) {
                // Simulation manquante
                $this->addFlash('error', 'La simulation est obligatoire.');
                return $this->redirectToRoute('admin_module_modifier', ['id' => $module->getId()]);
            }

            $em->flush();

            $this->addFlash('success', '✅ Module modifié avec succès !');
            return $this->redirectToRoute('admin_modules_liste');
        }

        return $this->render('admin/modules/modifier.html.twig', ['module' => $module]);
    }

    #[Route('/{id}/publier', name: 'admin_module_publier', methods: ['POST'])]
    public function publier(ModuleFormation $module, EntityManagerInterface $em): Response
    {
        // Vérifier les conditions de publication
        $chapitres = $module->getChapitres();
        if ($chapitres->count() < 3) {
            $this->addFlash('danger', '❌ Impossible de publier : un module doit contenir au moins 3 chapitres.');
            return $this->redirectToRoute('admin_modules_liste');
        }

        foreach ($chapitres as $chapitre) {
            if ($chapitre->getQuizQuestions() === null || empty($chapitre->getQuizQuestions())) {
                $this->addFlash('danger', '❌ Impossible de publier : le chapitre "' . $chapitre->getTitre() . '" n\'a pas de quiz.');
                return $this->redirectToRoute('admin_modules_liste');
            }
        }

        // Simulation déjà obligatoire (on suppose qu'elle existe)
        $module->setEstPublie(true);
        $em->flush();

        $this->addFlash('success', '✅ Module publié avec succès !');
        return $this->redirectToRoute('admin_modules_liste');
    }

    #[Route('/{id}/depublier', name: 'admin_module_depublier', methods: ['POST'])]
    public function depublier(ModuleFormation $module, EntityManagerInterface $em): Response
    {
        $module->setEstPublie(false);
        $em->flush();
        $this->addFlash('success', '✅ Module dépublié.');
        return $this->redirectToRoute('admin_modules_liste');
    }

    #[Route('/{id}/supprimer', name: 'admin_module_supprimer', methods: ['POST'])]
    public function supprimer(ModuleFormation $module, EntityManagerInterface $em): Response
    {
        if ($module->getChapitres()->count() > 0) {
            $this->addFlash('danger', '❌ Impossible : le module a des chapitres.');
            return $this->redirectToRoute('admin_modules_liste');
        }
        $em->remove($module);
        $em->flush();
        $this->addFlash('success', '✅ Module supprimé.');
        return $this->redirectToRoute('admin_modules_liste');
    }

    #[Route('/{id}', name: 'admin_module_details')]
    public function details(ModuleFormation $module): Response
    {
        return $this->render('admin/modules/details.html.twig', ['module' => $module]);
    }
}