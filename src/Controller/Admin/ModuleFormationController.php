<?php

namespace App\Controller\Admin;
use App\Entity\CampagneFormation;
use App\Entity\ModuleFormation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\SimulationInteractive;
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

    #[Route('/nouveau', name: 'admin_module_nouveau')]
    public function nouveau(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        if ($request->isMethod('POST')) {
            $errors = [];
            $titre = $request->request->get('titre');
            $description = $request->request->get('description');
            $categorieNom = $request->request->get('categorie_nom'); // champ texte

            if (empty($titre) || empty($description) || empty($categorieNom)) {
                $errors[] = 'Tous les champs obligatoires doivent être remplis.';
            }

            if (empty($errors)) {
                $module = new ModuleFormation();
                $module->setTitre($titre);
                $module->setSlug($slugger->slug($titre)->lower());
                $module->setDescription($description);
                $module->setCategorie($categorieNom); // stockage en string
                $module->setTypeModule('formation');
                $module->setDifficulte($request->request->get('difficulte', 'debutant'));
                $module->setDureeEstimee((int)($request->request->get('duree_estimee') ?? 30));
                $module->setPointsReussite((int)($request->request->get('points_reussite') ?? 100));
                $module->setEtoilesReussite((int)($request->request->get('etoiles_reussite') ?? 2));
                $module->setEstPublie($request->request->get('est_publie') ? true : false);

                $simulationId = $request->request->get('simulation_id');
                if ($simulationId) {
                    $simulation = $em->getRepository(SimulationInteractive::class)->find($simulationId);
                    if ($simulation) {
                        $ancienModule = $em->getRepository(ModuleFormation::class)->findOneBy(['simulation' => $simulation]);
                        if ($ancienModule) $ancienModule->setSimulation(null);
                        $module->setSimulation($simulation);
                    }
                }

                $em->persist($module);
                $em->flush();
                $this->addFlash('success', '✅ Module créé avec succès !');
                return $this->redirectToRoute('admin_module_details', ['id' => $module->getId()]);
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        $simulations = $em->getRepository(SimulationInteractive::class)->findBy([], ['titre' => 'ASC']);
        return $this->render('admin/modules/nouveau.html.twig', [
            'simulations' => $simulations,
        ]);
    }

    #[Route('/{id}/modifier', name: 'admin_module_modifier')]
    public function modifier(ModuleFormation $module, Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        if ($request->isMethod('POST')) {
            $errors = [];
            $titre = $request->request->get('titre');
            $description = $request->request->get('description');
            $categorieNom = $request->request->get('categorie_nom');

            if (empty($titre) || empty($description) || empty($categorieNom)) {
                $errors[] = 'Tous les champs obligatoires doivent être remplis.';
            }

            if (empty($errors)) {
                $module->setTitre($titre);
                $module->setSlug($slugger->slug($titre)->lower());
                $module->setDescription($description);
                $module->setCategorie($categorieNom);
                $module->setDifficulte($request->request->get('difficulte'));
                $module->setDureeEstimee((int)$request->request->get('duree_estimee'));
                $module->setPointsReussite((int)$request->request->get('points_reussite'));
                $module->setEtoilesReussite((int)$request->request->get('etoiles_reussite'));
                $module->setEstPublie($request->request->get('est_publie') ? true : false);
                $em->flush();
                $this->addFlash('success', '✅ Module modifié avec succès !');
                return $this->redirectToRoute('admin_modules_liste');
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->render('admin/modules/modifier.html.twig', ['module' => $module]);
    }

 #[Route('/{id}/supprimer', name: 'admin_module_supprimer', methods: ['POST'])]
public function supprimer(ModuleFormation $module, EntityManagerInterface $em): Response
{
    // 1. Vérifier si le module a des chapitres
    if ($module->getChapitres()->count() > 0) {
        $this->addFlash('danger', '❌ Impossible de supprimer ce module car il contient ' . $module->getChapitres()->count() . ' chapitre(s). Supprimez d’abord les chapitres.');
        return $this->redirectToRoute('admin_modules_liste');
    }

    // 2. Vérifier si le module est utilisé dans des campagnes de formation
    $campagnes = $em->getRepository(CampagneFormation::class)->createQueryBuilder('c')
        ->innerJoin('c.modules', 'm')
        ->where('m.id = :moduleId')
        ->setParameter('moduleId', $module->getId())
        ->getQuery()
        ->getResult();

    if (count($campagnes) > 0) {
        $this->addFlash('danger', '❌ Impossible de supprimer ce module car il est utilisé dans ' . count($campagnes) . ' campagne(s) de formation.');
        return $this->redirectToRoute('admin_modules_liste');
    }

    // 3. Suppression
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
    public function details(ModuleFormation $module, EntityManagerInterface $em): Response
    {
        $simulationsDisponibles = $em->getRepository(SimulationInteractive::class)->findBy(
            ['estPublie' => true],
            ['titre' => 'ASC']
        );
        return $this->render('admin/modules/details.html.twig', [
            'module'                  => $module,
            'simulationsDisponibles'  => $simulationsDisponibles,
        ]);
    }

    #[Route('/{id}/lier-simulation', name: 'admin_module_lier_simulation')]
    public function pageLierSimulation(ModuleFormation $module, EntityManagerInterface $em): Response
    {
        $simulations = $em->getRepository(SimulationInteractive::class)->findBy([], ['titre' => 'ASC']);
        return $this->render('admin/modules/lier_simulation.html.twig', [
            'module'      => $module,
            'simulations' => $simulations,
        ]);
    }

    #[Route('/{id}/simulation', name: 'admin_module_simulation', methods: ['POST'])]
    public function lierSimulation(ModuleFormation $module, Request $request, EntityManagerInterface $em): Response
    {
        $simulationId = $request->request->get('simulation_id');

        if ($simulationId === 'aucune') {
            if ($module->getSimulation()) {
                $module->getSimulation()->setModule(null);
                $module->setSimulation(null);
                $em->flush();
            }
            $this->addFlash('success', 'Simulation retirée du module.');
        } else {
            $simulation = $em->getRepository(SimulationInteractive::class)->find($simulationId);

            if ($simulation) {
                $moduleActuel = $simulation->getModule();

                // La simulation est déjà liée à ce même module → rien à faire
                if ($moduleActuel && $moduleActuel->getId() === $module->getId()) {
                    $this->addFlash('info', '✅ Cette simulation est déjà liée à ce module.');
                    return $this->redirectToRoute('admin_module_details', ['id' => $module->getId()]);
                }

                // La simulation est déjà liée à un AUTRE module → bloquer avec message clair
                if ($moduleActuel && $moduleActuel->getId() !== $module->getId()) {
                    $this->addFlash('error',
                        '⚠️ La simulation « ' . $simulation->getTitre() . ' » est déjà liée au module « '
                        . $moduleActuel->getTitre() . ' ». '
                        . 'Retirez-la d\'abord de cet autre module avant de la lier ici.'
                    );
                    return $this->redirectToRoute('admin_module_details', ['id' => $module->getId()]);
                }

                // Cas normal : simulation libre → on la lie
                $simulation->setModule($module);
                $module->setSimulation($simulation);
                $em->flush();
                $this->addFlash('success', '✅ Simulation « ' . $simulation->getTitre() . ' » liée au module !');
            }
        }

        return $this->redirectToRoute('admin_module_details', ['id' => $module->getId()]);
    }
}