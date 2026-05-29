<?php

namespace App\Controller\Admin;

use App\Entity\Employe;
use App\Entity\Entreprise;
use App\Entity\Utilisateur;
use App\Entity\ModuleFormation;
use App\Entity\CampagnePhishing;
use App\Entity\Departement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DashboardController extends AbstractController
{
    #[Route('/admin/dashboard', name: 'admin_dashboard')]
    #[IsGranted('ROLE_ADMIN')]
    public function dashboard(EntityManagerInterface $em): Response
    {
        // ══════════════════════════════════════════════════
        // 1. STATISTIQUES ENTREPRISES
        // ══════════════════════════════════════════════════

        $totalEntreprises      = $em->getRepository(Entreprise::class)->count([]);
        $entreprisesActives    = $em->getRepository(Entreprise::class)->countByStatut('ACTIF');
        $entreprisesEnAttente  = $em->getRepository(Entreprise::class)->countByStatut('EN_ATTENTE');
        $entreprisesSuspendues = $em->getRepository(Entreprise::class)->countByStatut('SUSPENDU');

        // ══════════════════════════════════════════════════
        // 2. STATISTIQUES UTILISATEURS
        // ══════════════════════════════════════════════════

        // Total des RSSI actifs sur toute la plateforme
        $totalRSSI = (int) $em->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(Utilisateur::class, 'u')
            ->where('u INSTANCE OF App\Entity\RSSI')
            ->andWhere('u.estActif = :actif')
            ->setParameter('actif', true)
            ->getQuery()
            ->getSingleScalarResult();

        // Total des employés actifs sur toute la plateforme
        $totalEmployesActifs = (int) $em->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(Utilisateur::class, 'u')
            ->where('u INSTANCE OF App\Entity\Employe')
            ->andWhere('u.estActif = :actif')
            ->setParameter('actif', true)
            ->getQuery()
            ->getSingleScalarResult();

        // Total tous rôles confondus
        $totalUtilisateurs = $em->getRepository(Utilisateur::class)->count([]);

        // ══════════════════════════════════════════════════
        // 3. STATISTIQUES MODULES DE FORMATION
        // ══════════════════════════════════════════════════

        $totalModules   = $em->getRepository(ModuleFormation::class)->count([]);
        $modulesPublies = $em->getRepository(ModuleFormation::class)->count(['estPublie' => true]);

        // ══════════════════════════════════════════════════
        // 4. STATISTIQUES CAMPAGNES PHISHING
        // ══════════════════════════════════════════════════

        $totalCampagnesPhishing = $em->getRepository(CampagnePhishing::class)->count([]);
        $campagnesEnCours       = $em->getRepository(CampagnePhishing::class)->count(['statut' => 'EN_COURS']);

        // ══════════════════════════════════════════════════
        // 5. ÉVOLUTION DES ENTREPRISES (12 DERNIERS MOIS)
        // ══════════════════════════════════════════════════

        $evolutionEntreprises = [];

        for ($i = 11; $i >= 0; $i--) {
            $dateDebut = new \DateTime("first day of -{$i} months");
            $dateDebut->setTime(0, 0, 0);

            $dateFin = new \DateTime("last day of -{$i} months");
            $dateFin->setTime(23, 59, 59);

            $mois = $dateDebut->format('M Y');

            $count = (int) $em->createQueryBuilder()
                ->select('COUNT(e.id)')
                ->from(Entreprise::class, 'e')
                ->where('e.dateCreation BETWEEN :debut AND :fin')
                ->setParameter('debut', $dateDebut)
                ->setParameter('fin', $dateFin)
                ->getQuery()
                ->getSingleScalarResult();

            $evolutionEntreprises[] = [
                'mois'  => $mois,
                'total' => $count,
            ];
        }

        // ══════════════════════════════════════════════════
        // 6. RÉPARTITION PAR SECTEUR (entreprises actives)
        // ══════════════════════════════════════════════════

        $entreprisesParSecteur = $em->createQueryBuilder()
            ->select('e.secteur, COUNT(e.id) as total')
            ->from(Entreprise::class, 'e')
            ->where('e.statut = :statut')
            ->setParameter('statut', 'ACTIF')
            ->groupBy('e.secteur')
            ->orderBy('total', 'DESC')
            ->setMaxResults(6)
            ->getQuery()
            ->getResult();

        // ══════════════════════════════════════════════════
        // 7. DERNIÈRES ENTREPRISES INSCRITES
        // ══════════════════════════════════════════════════

        $dernieresEntreprises = $em->getRepository(Entreprise::class)
            ->findBy([], ['dateCreation' => 'DESC'], 5);

        // ══════════════════════════════════════════════════
        // 8. CLASSEMENT DES ENTREPRISES (par nb d'employés
        //    actifs + score moyen de vigilance)
        // ══════════════════════════════════════════════════

        // On récupère toutes les entreprises actives
        $entreprisesActivesList = $em->getRepository(Entreprise::class)
            ->findBy(['statut' => 'ACTIF'], ['nom' => 'ASC']);

        $classement = [];

        foreach ($entreprisesActivesList as $entreprise) {
            // Score moyen de vigilance des employés de cette entreprise
            $scoreResult = $em->createQueryBuilder()
                ->select('AVG(emp.scoreVigilance) as scoreMoyen, COUNT(emp.id) as nbEmployes')
                ->from(Employe::class, 'emp')
                ->join('emp.departement', 'd')
                ->where('d.entreprise = :ent')
                ->setParameter('ent', $entreprise)
                ->getQuery()
                ->getOneOrNullResult();

            $scoreMoyen  = $scoreResult ? round((float)($scoreResult['scoreMoyen'] ?? 50), 1) : 50.0;
            $nbEmployes  = $scoreResult ? (int)($scoreResult['nbEmployes'] ?? 0) : 0;

            $classement[] = [
                'entreprise' => $entreprise,
                'scoreMoyen' => $scoreMoyen,
                'nbEmployes' => $nbEmployes,
            ];
        }

        // Tri décroissant par score moyen
        usort($classement, fn($a, $b) => $b['scoreMoyen'] <=> $a['scoreMoyen']);

        // On garde le top 5
        $classementEntreprises = array_slice($classement, 0, 5);

        // ══════════════════════════════════════════════════
        // 9. ENTREPRISES VULNÉRABLES
        //    (score moyen < 50 ou plus de 30 % d'employés
        //     à risque élevé — score < 35)
        // ══════════════════════════════════════════════════

        $entreprisesVulnerables = [];

        foreach ($entreprisesActivesList as $entreprise) {
            // Récupérer tous les employés de cette entreprise
            $employes = $em->createQueryBuilder()
                ->select('emp')
                ->from(Employe::class, 'emp')
                ->join('emp.departement', 'd')
                ->where('d.entreprise = :ent')
                ->andWhere('emp.estActif = :actif')
                ->setParameter('ent', $entreprise)
                ->setParameter('actif', true)
                ->getQuery()
                ->getResult();

            $total = count($employes);
            if ($total === 0) {
                continue;
            }

            $scores      = array_map(fn($e) => $e->getScoreVigilance(), $employes);
            $scoreMoyen  = round(array_sum($scores) / $total, 1);
            $nbARisque   = count(array_filter($employes, fn($e) => $e->getScoreVigilance() < 35));
            $pctRisque   = round(($nbARisque / $total) * 100, 1);

            // Critère de vulnérabilité : score moyen < 50 OU + de 30 % d'employés à risque élevé
            if ($scoreMoyen < 50 || $pctRisque >= 30) {
                $entreprisesVulnerables[] = [
                    'entreprise'  => $entreprise,
                    'nom'         => $entreprise->getNom(),
                    'score'       => $scoreMoyen,
                    'pctRisque'   => $pctRisque,
                    'nbARisque'   => $nbARisque,
                    'totalEmploye' => $total,
                ];
            }
        }

        // Tri croissant : les plus vulnérables en premier
        usort($entreprisesVulnerables, fn($a, $b) => $a['score'] <=> $b['score']);

        // On garde les 6 plus vulnérables
        $entreprisesVulnerables = array_slice($entreprisesVulnerables, 0, 6);

        // ══════════════════════════════════════════════════
        // 10. RENDU
        // ══════════════════════════════════════════════════

        return $this->render('admin/dashboard.html.twig', [

            // --- KPI généraux ---
            'stats' => [
                'totalEntreprises'      => $totalEntreprises,
                'entreprisesActives'    => $entreprisesActives,
                'entreprisesEnAttente'  => $entreprisesEnAttente,
                'entreprisesSuspendues' => $entreprisesSuspendues,
                'totalUtilisateurs'     => $totalUtilisateurs,
                'totalRSSI'             => $totalRSSI,
                'totalEmployesActifs'   => $totalEmployesActifs,
                'totalModules'          => $totalModules,
                'modulesPublies'        => $modulesPublies,
                'totalCampagnesPhishing' => $totalCampagnesPhishing,
                'campagnesEnCours'      => $campagnesEnCours,
            ],

            // --- Graphiques ---
            'evolutionEntreprises'   => $evolutionEntreprises,
            'entreprisesParSecteur'  => $entreprisesParSecteur,

            // --- Tableaux ---
            'dernieresEntreprises'   => $dernieresEntreprises,

            // --- Classement (top 5 par score moyen de vigilance) ---
            'classementEntreprises'  => $classementEntreprises,

            // --- Entreprises à risque ---
            'entreprisesVulnerables' => $entreprisesVulnerables,
        ]);
    }
}