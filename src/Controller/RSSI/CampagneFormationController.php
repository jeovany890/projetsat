<?php

namespace App\Controller\RSSI;

use App\Entity\CampagneFormation;
use App\Entity\Departement;
use App\Entity\Employe;
use App\Entity\ModuleFormation;
use App\Entity\ProgressionModule;
use App\Entity\RSSI;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rssi/formations')]
#[IsGranted('ROLE_RSSI')]
class CampagneFormationController extends AbstractController
{
    private function getRssi(): RSSI
    {
        /** @var RSSI $rssi */
        return $this->getUser();
    }

    private function getEntreprise(): ?\App\Entity\Entreprise
    {
        return $this->getRssi()->getEntreprise();
    }

    // ============================================================
    // LISTE
    // ============================================================
    #[Route('', name: 'rssi_formations_liste')]
    public function liste(EntityManagerInterface $em): Response
    {
        $campagnes = $em->getRepository(CampagneFormation::class)
            ->findBy(['rssi' => $this->getRssi()], ['dateCreation' => 'DESC']);

        // Mettre à jour automatiquement les statuts
        foreach ($campagnes as $campagne) {
            $this->mettreAJourStatut($campagne, $em);
        }
        $em->flush();

        return $this->render('rssi/formations/liste.html.twig', [
            'campagnes' => $campagnes,
        ]);
    }

    // ============================================================
    // CRÉER
    // ============================================================
    #[Route('/nouvelle', name: 'rssi_formations_nouvelle', methods: ['GET', 'POST'])]
    public function nouvelle(Request $request, EntityManagerInterface $em): Response
    {
        $entreprise = $this->getEntreprise();

        // Modules publiés disponibles
        $modules = $em->getRepository(ModuleFormation::class)
            ->findBy(['estPublie' => true], ['ordreAffichage' => 'ASC']);

        // Départements de l'entreprise
        $departements = $entreprise
            ? $em->getRepository(Departement::class)->findBy(['entreprise' => $entreprise], ['nom' => 'ASC'])
            : [];

        if ($request->isMethod('POST')) {
            $titre          = trim($request->request->get('titre', ''));
            $description    = trim($request->request->get('description', ''));
            $trimestre      = $request->request->get('trimestre', 'T1');
            $annee          = (int) $request->request->get('annee', date('Y'));
            $dateDebut      = $request->request->get('date_debut');
            $dateFin        = $request->request->get('date_fin');
            $pointsPenalite = (int) $request->request->get('points_penalite', 50);
            $moduleIds      = $request->request->all('modules') ?: [];
            $deptIds        = $request->request->all('departements') ?: [];
            $tousEmployes   = $request->request->get('tous_employes') === '1';

            if (empty($titre) || empty($dateDebut) || empty($dateFin) || empty($moduleIds)) {
                $this->addFlash('error', 'Titre, dates et au moins un module sont obligatoires.');
                return $this->render('rssi/formations/nouvelle.html.twig', compact('modules', 'departements'));
            }

            $campagne = new CampagneFormation();
            $campagne->setTitre($titre);
            $campagne->setDescription($description ?: null);
            $campagne->setTrimestre($trimestre);
            $campagne->setAnnee($annee);
            $campagne->setDateDebut(new \DateTime($dateDebut));
            $campagne->setDateFin(new \DateTime($dateFin));
            $campagne->setPointsPenalite($pointsPenalite);
            $campagne->setRssi($this->getRssi());
            $campagne->setStatut('PLANIFIEE');

            // Ajouter les modules sélectionnés
            foreach ($moduleIds as $moduleId) {
                $module = $em->getRepository(ModuleFormation::class)->find($moduleId);
                if ($module) {
                    $campagne->addModule($module);
                }
            }

            $em->persist($campagne);

            // Déterminer les employés cibles
            $employes = $this->getEmployesCibles($em, $entreprise, $tousEmployes, $deptIds);

            // Créer les progressions pour chaque employé × module
            $totalParticipants = count($employes);
            foreach ($employes as $employe) {
                foreach ($moduleIds as $moduleId) {
                    $module = $em->getRepository(ModuleFormation::class)->find($moduleId);
                    if (!$module) continue;

                    $progression = new ProgressionModule();
                    $progression->setEmploye($employe);
                    $progression->setModule($module);
                    $progression->setCampagne($campagne);
                    $progression->setStatut('NON_COMMENCE');
                    $progression->setEcheance(new \DateTime($dateFin));
                    $em->persist($progression);
                }
            }

            $campagne->setTotalParticipants($totalParticipants);
            $em->flush();

            $this->addFlash('success', "Campagne « {$titre} » créée avec {$totalParticipants} participant(s).");
            return $this->redirectToRoute('rssi_formations_liste');
        }

        return $this->render('rssi/formations/nouvelle.html.twig', [
            'modules'      => $modules,
            'departements' => $departements,
            'annee'        => (int) date('Y'),
        ]);
    }

    // ============================================================
    // DÉTAIL
    // ============================================================
    #[Route('/{id}', name: 'rssi_formations_detail')]
    public function detail(CampagneFormation $campagne, EntityManagerInterface $em): Response
    {
        $this->verifierAcces($campagne);
        $this->mettreAJourStatut($campagne, $em);
        $em->flush();

        // Progressions groupées par employé
        $progressions = $campagne->getProgressions();

        // Stats par module
        $statsModules = [];
        foreach ($campagne->getModules() as $module) {
            $progsModule = $progressions->filter(fn($p) => $p->getModule()->getId() === $module->getId());
            $total     = $progsModule->count();
            $termines  = $progsModule->filter(fn($p) => $p->getStatut() === 'TERMINE')->count();
            $enCours   = $progsModule->filter(fn($p) => $p->getStatut() === 'EN_COURS')->count();
            $statsModules[] = [
                'module'   => $module,
                'total'    => $total,
                'termines' => $termines,
                'enCours'  => $enCours,
                'pct'      => $total > 0 ? round(($termines / $total) * 100) : 0,
            ];
        }

        // Progressions par employé (regroupées)
        $parEmploye = [];
        foreach ($progressions as $p) {
            $eid = $p->getEmploye()->getId();
            if (!isset($parEmploye[$eid])) {
                $parEmploye[$eid] = [
                    'employe'     => $p->getEmploye(),
                    'progressions' => [],
                    'termines'    => 0,
                    'total'       => 0,
                    'retard'      => false,
                ];
            }
            $parEmploye[$eid]['progressions'][] = $p;
            $parEmploye[$eid]['total']++;
            if ($p->getStatut() === 'TERMINE') $parEmploye[$eid]['termines']++;
            if ($p->isEstEnRetard()) $parEmploye[$eid]['retard'] = true;
        }

        return $this->render('rssi/formations/detail.html.twig', [
            'campagne'     => $campagne,
            'statsModules' => $statsModules,
            'parEmploye'   => $parEmploye,
        ]);
    }

    // ============================================================
    // CHANGER STATUT
    // ============================================================
    #[Route('/{id}/statut/{statut}', name: 'rssi_formations_statut', methods: ['POST'])]
    public function changerStatut(CampagneFormation $campagne, string $statut, EntityManagerInterface $em): Response
    {
        $this->verifierAcces($campagne);

        $statutsValides = ['PLANIFIEE', 'EN_COURS', 'TERMINEE', 'ANNULEE'];
        if (!in_array($statut, $statutsValides)) {
            $this->addFlash('error', 'Statut invalide.');
            return $this->redirectToRoute('rssi_formations_detail', ['id' => $campagne->getId()]);
        }

        $campagne->setStatut($statut);
        $em->flush();

        $this->addFlash('success', 'Statut de la campagne mis à jour.');
        return $this->redirectToRoute('rssi_formations_detail', ['id' => $campagne->getId()]);
    }

    // ============================================================
    // SUPPRIMER
    // ============================================================
    #[Route('/{id}/supprimer', name: 'rssi_formations_supprimer', methods: ['POST'])]
    public function supprimer(CampagneFormation $campagne, EntityManagerInterface $em): Response
    {
        $this->verifierAcces($campagne);

        if ($campagne->getStatut() === 'EN_COURS') {
            $this->addFlash('error', 'Impossible de supprimer une campagne en cours.');
            return $this->redirectToRoute('rssi_formations_liste');
        }

        $titre = $campagne->getTitre();
        $em->remove($campagne);
        $em->flush();

        $this->addFlash('success', "Campagne « {$titre} » supprimée.");
        return $this->redirectToRoute('rssi_formations_liste');
    }

    // ============================================================
    // HELPERS PRIVÉS
    // ============================================================
    private function verifierAcces(CampagneFormation $campagne): void
    {
        if ($campagne->getRssi() !== $this->getRssi()) {
            throw $this->createAccessDeniedException();
        }
    }

    private function mettreAJourStatut(CampagneFormation $campagne, EntityManagerInterface $em): void
    {
        if (in_array($campagne->getStatut(), ['ANNULEE', 'TERMINEE'])) return;

        $now = new \DateTime();
        if ($now < $campagne->getDateDebut()) {
            $campagne->setStatut('PLANIFIEE');
        } elseif ($now > $campagne->getDateFin()) {
            $campagne->setStatut('TERMINEE');
        } else {
            $campagne->setStatut('EN_COURS');
        }

        // Recalculer stats
        $progressions = $campagne->getProgressions();
        $termines  = $progressions->filter(fn($p) => $p->getStatut() === 'TERMINE')->count();
        $enCours   = $progressions->filter(fn($p) => $p->getStatut() === 'EN_COURS')->count();
        $enRetard  = $progressions->filter(fn($p) => $p->isEstEnRetard())->count();

        $campagne->setNombreTermines($termines);
        $campagne->setNombreEnCours($enCours);
        $campagne->setNombreEnRetard($enRetard);
    }

    private function getEmployesCibles(
        EntityManagerInterface $em,
        ?\App\Entity\Entreprise $entreprise,
        bool $tousEmployes,
        array $deptIds
    ): array {
        if (!$entreprise) return [];

        $qb = $em->createQueryBuilder()
            ->select('e')->from(Employe::class, 'e')
            ->join('e.departement', 'd')
            ->where('d.entreprise = :entreprise AND e.estActif = true')
            ->setParameter('entreprise', $entreprise);

        if (!$tousEmployes && !empty($deptIds)) {
            $qb->andWhere('d.id IN (:depts)')->setParameter('depts', $deptIds);
        }

        return $qb->getQuery()->getResult();
    }
}
