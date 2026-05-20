<?php

namespace App\Controller\Admin;

use App\Entity\ModuleFormation;
use App\Entity\SimulationInteractive;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/simulations')]
#[IsGranted('ROLE_ADMIN')]
class SimulationController extends AbstractController
{
    // Types de simulation disponibles
    const TYPES = [
        'GMAIL'     => 'Gmail — Boîte email',
        'SMS'       => 'SMS — Messagerie mobile',
        'WHATSAPP'  => 'WhatsApp — Messagerie',
    ];

    #[Route('', name: 'admin_simulations_liste')]
    public function liste(EntityManagerInterface $em): Response
    {
        $simulations = $em->getRepository(SimulationInteractive::class)->findBy(
            [], ['dateCreation' => 'DESC']
        );
        return $this->render('admin/simulations/liste.html.twig', [
            'simulations' => $simulations,
            'types'       => self::TYPES,
        ]);
    }

    #[Route('/nouvelle', name: 'admin_simulation_nouvelle', methods: ['GET', 'POST'])]
    public function nouvelle(
        Request $request,
        EntityManagerInterface $em,
        ?int $module_id = null
    ): Response {
        $module = null;
        if ($module_id) {
            $module = $em->getRepository(ModuleFormation::class)->find($module_id);
        }

        if ($request->isMethod('POST')) {
            $type = $request->request->get('type_simulation');

            // Construire le contenu selon le type
            $contenu = $this->construireContenu($type, $request);

            $simulation = new SimulationInteractive();
            $simulation->setTitre($request->request->get('titre'))
                ->setDescription($request->request->get('description'))
                ->setTypeSimulation($type)
                ->setDifficulte($request->request->get('difficulte', 'moyen'))
                ->setDureeEstimee((int)$request->request->get('duree_estimee', 10))
                ->setPointsReussite((int)$request->request->get('points_reussite', 100))
                ->setContenuSimulation($contenu)
                ->setEstPublie($request->request->get('est_publie') ? true : false);

            // Associer au module si demandé
            if ($module) {
                $simulation->setModule($module);
            }

            $em->persist($simulation);
            $em->flush();

            $this->addFlash('success', ' Simulation créée !');

            // Redirection : si module_id présent, retourner sur le module
            if ($module) {
                return $this->redirectToRoute('admin_module_details', ['id' => $module->getId()]);
            }
            return $this->redirectToRoute('admin_simulations_liste');
        }

        return $this->render('admin/simulations/nouvelle.html.twig', [
            'types'  => self::TYPES,
            'module' => $module,
        ]);
    }

    #[Route('/{id}/modifier', name: 'admin_simulation_modifier', methods: ['GET', 'POST'])]
    public function modifier(SimulationInteractive $simulation, Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $type = $request->request->get('type_simulation');
            $contenu = $this->construireContenu($type, $request);

            $simulation->setTitre($request->request->get('titre'))
                ->setDescription($request->request->get('description'))
                ->setTypeSimulation($type)
                ->setDifficulte($request->request->get('difficulte', 'moyen'))
                ->setDureeEstimee((int)$request->request->get('duree_estimee', 10))
                ->setPointsReussite((int)$request->request->get('points_reussite', 100))
                ->setContenuSimulation($contenu)
                ->setEstPublie($request->request->get('est_publie') ? true : false);

            $em->flush();
            $this->addFlash('success', 'Simulation modifiée !');
            return $this->redirectToRoute('admin_simulations_liste');
        }

        return $this->render('admin/simulations/modifier.html.twig', [
            'simulation' => $simulation,
            'types'      => self::TYPES,
        ]);
    }

    #[Route('/{id}/toggle', name: 'admin_simulation_toggle', methods: ['POST'])]
    public function toggle(SimulationInteractive $simulation, EntityManagerInterface $em): Response
    {
        $simulation->setEstPublie(!$simulation->isEstPublie());
        $em->flush();
        $this->addFlash('success', $simulation->isEstPublie() ? ' Publiée !' : 'Dépubliée.');
        return $this->redirectToRoute('admin_simulations_liste');
    }

    #[Route('/{id}/supprimer', name: 'admin_simulation_supprimer', methods: ['POST'])]
    public function supprimer(SimulationInteractive $simulation, EntityManagerInterface $em): Response
    {
        $titre = $simulation->getTitre();
        $em->remove($simulation);
        $em->flush();
        $this->addFlash('success', "Simulation « {$titre} » supprimée.");
        return $this->redirectToRoute('admin_simulations_liste');
    }

    // ────────────────────────────────────────────
    // Construit le contenu JSON selon le type
    // ────────────────────────────────────────────
    private function construireContenu(string $type, Request $request): array
    {
        $jsonBrut = $request->request->get('contenu_simulation_json', '');
        if ($jsonBrut) {
            $decoded = json_decode($jsonBrut, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }
        return match($type) {
            'GMAIL'    => ['type' => 'gmail_banque',    'nb_a_tirer' => 4, 'emails'        => []],
            'SMS'      => ['type' => 'sms_banque',      'nb_a_tirer' => 5, 'sms'           => []],
            'WHATSAPP' => ['type' => 'whatsapp_banque', 'nb_a_tirer' => 5, 'conversations' => []],
            default    => ['type' => $type, 'contenu' => []],
        };
    }
}