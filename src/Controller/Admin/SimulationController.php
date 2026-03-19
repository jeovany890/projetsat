<?php

namespace App\Controller\Admin;

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
        'GMAIL'         => 'Fausse boîte Gmail',
        'MOT_DE_PASSE'  => 'Faux formulaire mot de passe',
        'FORMULAIRE'    => 'Faux formulaire de données',
        'LIEN_SUSPECT'  => 'Détection de lien suspect',
        'PIECE_JOINTE'  => 'Fausse pièce jointe malveillante',
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
    public function nouvelle(Request $request, EntityManagerInterface $em): Response
    {
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
                ->setPointsEchec((int)$request->request->get('points_echec', 0))
                ->setContenuSimulation($contenu)
                ->setEstPublie($request->request->get('est_publie') ? true : false);

            $em->persist($simulation);
            $em->flush();

            $this->addFlash('success', '✅ Simulation créée !');
            return $this->redirectToRoute('admin_simulations_liste');
        }

        return $this->render('admin/simulations/nouvelle.html.twig', [
            'types' => self::TYPES,
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
                ->setPointsEchec((int)$request->request->get('points_echec', 0))
                ->setContenuSimulation($contenu)
                ->setEstPublie($request->request->get('est_publie') ? true : false);

            $em->flush();
            $this->addFlash('success', '✅ Simulation modifiée !');
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
        $this->addFlash('success', $simulation->isEstPublie() ? '✅ Publiée !' : 'Dépubliée.');
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
        return match($type) {
            'GMAIL' => [
                'expediteur_nom'    => $request->request->get('gmail_expediteur_nom', 'Google Security'),
                'expediteur_email'  => $request->request->get('gmail_expediteur_email', 'security@google.com'),
                'sujet'             => $request->request->get('gmail_sujet', 'Activité suspecte sur votre compte'),
                'corps'             => $request->request->get('gmail_corps', ''),
                'lien_piege_texte'  => $request->request->get('gmail_lien_texte', 'Sécuriser mon compte'),
                'indices'           => array_filter(explode("\n", $request->request->get('indices', ''))),
                'explication'       => $request->request->get('explication', ''),
            ],
            'MOT_DE_PASSE' => [
                'site_nom'          => $request->request->get('mdp_site_nom', 'BOA Bénin'),
                'site_url_affiche'  => $request->request->get('mdp_site_url', 'https://boa-benin.securite.com'),
                'logo_emoji'        => $request->request->get('mdp_logo', '🏦'),
                'message'           => $request->request->get('mdp_message', 'Votre mot de passe a expiré'),
                'indices'           => array_filter(explode("\n", $request->request->get('indices', ''))),
                'explication'       => $request->request->get('explication', ''),
            ],
            'LIEN_SUSPECT' => [
                'liens'             => array_filter(explode("\n", $request->request->get('liens', ''))),
                'lien_piege_index'  => (int)$request->request->get('lien_piege_index', 0),
                'indices'           => array_filter(explode("\n", $request->request->get('indices', ''))),
                'explication'       => $request->request->get('explication', ''),
            ],
            default => [
                'message'     => $request->request->get('contenu_message', ''),
                'indices'     => array_filter(explode("\n", $request->request->get('indices', ''))),
                'explication' => $request->request->get('explication', ''),
            ],
        };
    }
}