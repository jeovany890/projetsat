<?php

namespace App\Controller\RSSI;

use App\Entity\CampagneFormation;
use App\Entity\CampagnePhishing;
use App\Entity\Departement;
use App\Entity\Employe;
use App\Entity\ProgressionModule;
use App\Entity\ResultatPhishing;
use App\Entity\RSSI;
use App\Repository\CampagnePhishingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rssi')]
#[IsGranted('ROLE_RSSI')]
class DashboardController extends AbstractController
{
    #[Route('', name: 'rssi_dashboard')]
    public function index(EntityManagerInterface $em, CampagnePhishingRepository $campagneRepo): Response
    {
        /** @var RSSI $rssi */
        $rssi       = $this->getUser();
        $entreprise = $rssi->getEntreprise();

        // Guard
        $vide = [
            'totalEmployes'          => 0,
            'totalActifs'            => 0,
            'totalDepartements'      => 0,
            'scoreMoyen'             => 0,
            'riskGlobal'             => 'faible',
            'riskCouleur'            => '#10B981',
            'nbRisqueEleve'          => 0,
            'clickRate'              => 0,
            'submissionRate'         => 0,
            'reportRate'             => 0,
            'totalEnvoyes'           => 0,
            'campagnesPhishing'      => [],
            'campagnesLabels'        => '[]',
            'campagnesClics'         => '[]',
            'campagnesSubmissions'   => '[]',
            'campagnesSignalements'  => '[]',
            'departements'           => [],
            'topVulnerabilites'      => [],
            'formationsIndiv'        => [],
            'topEmployes'            => [],
            'alertes'                => [],
            'activiteRecente'        => [],
        ];

        if (!$entreprise) {
            return $this->render('rssi/dashboard.html.twig', $vide);
        }

        // ── Employés ────────────────────────────────────────────
        $employes = $em->createQueryBuilder()
            ->select('e')
            ->from(Employe::class, 'e')
            ->join('e.departement', 'd')
            ->where('d.entreprise = :ent')
            ->setParameter('ent', $entreprise)
            ->getQuery()->getResult();

        $totalEmployes = count($employes);
        $totalActifs   = count(array_filter($employes, fn($e) => $e->isEstActif()));

        $totalDepartements = (int) $em->createQueryBuilder()
            ->select('COUNT(d.id)')->from(Departement::class, 'd')
            ->where('d.entreprise = :ent')->setParameter('ent', $entreprise)
            ->getQuery()->getSingleScalarResult();

        // ── Score moyen & profil de risque ──────────────────────
        $scoreMoyen = 0;
        $nbRisqueEleve = 0;
        if ($totalEmployes > 0) {
            $scores     = array_map(fn($e) => $e->getScoreVigilance(), $employes);
            $scoreMoyen = round(array_sum($scores) / $totalEmployes, 1);
            $nbRisqueEleve = count(array_filter($employes, fn($e) => $e->getScoreVigilance() < 35));
        }

        $pctRisque   = $totalEmployes > 0 ? ($nbRisqueEleve / $totalEmployes) * 100 : 0;
        $riskGlobal  = $pctRisque >= 30 ? 'élevé' : ($pctRisque >= 15 ? 'moyen' : 'faible');
        $riskCouleur = match($riskGlobal) {
            'élevé'  => '#EF4444',
            'moyen'  => '#F59E0B',
            default  => '#10B981',
        };

        // ── Stats phishing globales ──────────────────────────────
        $campagnesPhishing = $campagneRepo->findBy(['rssi' => $rssi], ['id' => 'DESC']);
        $employeIds        = array_map(fn($e) => $e->getId(), $employes);

        $totalEnvoyes = 0; $totalClics = 0; $totalSoumissions = 0; $totalSignalements = 0;

        if (!empty($employeIds)) {
            $resultatStats = $em->createQueryBuilder()
                ->select(
                    'SUM(CASE WHEN r.emailEnvoye = true THEN 1 ELSE 0 END) as envoyes',
                    'SUM(CASE WHEN r.lienClique = true THEN 1 ELSE 0 END) as clics',
                    'SUM(CASE WHEN r.soumissionDonnees = true THEN 1 ELSE 0 END) as soumissions',
                    'SUM(CASE WHEN r.signale = true THEN 1 ELSE 0 END) as signalements'
                )
                ->from(ResultatPhishing::class, 'r')
                ->join('r.employe', 'e')
                ->where('e.id IN (:ids)')
                ->setParameter('ids', $employeIds)
                ->getQuery()->getSingleResult();

            $totalEnvoyes     = (int)($resultatStats['envoyes'] ?? 0);
            $totalClics       = (int)($resultatStats['clics'] ?? 0);
            $totalSoumissions = (int)($resultatStats['soumissions'] ?? 0);
            $totalSignalements= (int)($resultatStats['signalements'] ?? 0);
        }

        $clickRate      = $totalEnvoyes > 0 ? round(($totalClics / $totalEnvoyes) * 100, 1) : 0;
        $submissionRate = $totalEnvoyes > 0 ? round(($totalSoumissions / $totalEnvoyes) * 100, 1) : 0;
        $reportRate     = $totalEnvoyes > 0 ? round(($totalSignalements / $totalEnvoyes) * 100, 1) : 0;

        // ── Données graphique campagnes (6 dernières) ────────────
        $campagnesDonnees = array_slice($campagnesPhishing, 0, 6);
        $campagnesLabels  = []; $campagnesClics = []; $campagnesSubs = []; $campagnesSig = [];

        foreach (array_reverse($campagnesDonnees) as $c) {
            $st = $campagneRepo->getStats($c);
            $env = $st['emailsEnvoyes'] ?: 1;
            $campagnesLabels[] = substr($c->getTitre(), 0, 16) . (strlen($c->getTitre()) > 16 ? '…' : '');
            $campagnesClics[]  = round(($st['liensCliques'] / $env) * 100, 1);
            $campagnesSubs[]   = round(($st['soumissions']  / $env) * 100, 1);
            $campagnesSig[]    = round(($st['emailsSignales']/ $env) * 100, 1);
        }

        // ── Stats par département ────────────────────────────────
        $departements = $em->getRepository(Departement::class)
            ->findBy(['entreprise' => $entreprise], ['nom' => 'ASC']);

        $deptStats = [];
        foreach ($departements as $dept) {
            $emps  = $dept->getEmployes()->toArray();
            $nb    = count($emps);
            if ($nb === 0) { $deptStats[] = ['dept'=>$dept,'score'=>0,'nb'=>0,'risque'=>0]; continue; }
            $scores = array_map(fn($e) => $e->getScoreVigilance(), $emps);
            $moy    = round(array_sum($scores) / $nb, 1);
            $risque = count(array_filter($emps, fn($e) => $e->getScoreVigilance() < 35));
            $deptStats[] = ['dept'=>$dept, 'score'=>$moy, 'nb'=>$nb, 'risque'=>$risque];
        }

        // ── Top vulnérabilités (employés les + faibles) ──────────
        $tousEmployes = $employes;
        usort($tousEmployes, fn($a, $b) => $a->getScoreVigilance() <=> $b->getScoreVigilance());
        $topVulnerabilites = array_slice($tousEmployes, 0, 5);

        // ── Formations individuelles (campagne IS NULL) ──────────
        $formationsIndiv = [];
        if (!empty($employeIds)) {
            $formationsIndiv = $em->createQueryBuilder()
                ->select('p')
                ->from(ProgressionModule::class, 'p')
                ->where('p.campagne IS NULL')
                ->andWhere('p.employe IN (:ids)')
                ->andWhere('p.statut != :done')
                ->setParameter('ids', $employeIds)
                ->setParameter('done', 'TERMINE')
                ->orderBy('p.dateDebut', 'DESC')
                ->setMaxResults(5)
                ->getQuery()->getResult();
        }

        // ── Top employés (meilleur score) ────────────────────────
        $top = $employes;
        usort($top, fn($a, $b) => $b->getScoreVigilance() <=> $a->getScoreVigilance());
        $topEmployes = array_slice($top, 0, 5);

        // ── Alertes (employés à risque élevé + formations non commencées depuis > 3j) ──
        $alertes = [];
        foreach ($employes as $e) {
            if ($e->getScoreVigilance() < 25) {
                $alertes[] = [
                    'type'    => 'danger',
                    'employe' => $e,
                    'message' => 'Score critique : ' . $e->getScoreVigilance() . '/100',
                ];
            }
        }
        if (!empty($employeIds)) {
            $nonCommences = $em->createQueryBuilder()
                ->select('p')
                ->from(ProgressionModule::class, 'p')
                ->where('p.employe IN (:ids)')
                ->andWhere('p.statut = :nc')
                ->andWhere('p.dateDebut < :seuil')
                ->setParameter('ids', $employeIds)
                ->setParameter('nc', 'NON_COMMENCE')
                ->setParameter('seuil', new \DateTime('-3 days'))
                ->setMaxResults(3)
                ->getQuery()->getResult();
            foreach ($nonCommences as $p) {
                $alertes[] = [
                    'type'    => 'warning',
                    'employe' => $p->getEmploye(),
                    'message' => 'Formation non commencée : « ' . $p->getModule()->getTitre() . ' »',
                ];
            }
        }
        $alertes = array_slice($alertes, 0, 5);

        // ── Activité récente (derniers résultats phishing) ───────
        $activiteRecente = [];
        if (!empty($employeIds)) {
            $activiteRecente = $em->createQueryBuilder()
                ->select('r')
                ->from(ResultatPhishing::class, 'r')
                ->join('r.employe', 'e')
                ->where('e.id IN (:ids)')
                ->setParameter('ids', $employeIds)
                ->orderBy('r.dateClic', 'DESC')
                ->setMaxResults(8)
                ->getQuery()->getResult();
        }

        return $this->render('rssi/dashboard.html.twig', [
            'totalEmployes'         => $totalEmployes,
            'totalActifs'           => $totalActifs,
            'totalDepartements'     => $totalDepartements,
            'scoreMoyen'            => $scoreMoyen,
            'riskGlobal'            => $riskGlobal,
            'riskCouleur'           => $riskCouleur,
            'nbRisqueEleve'         => $nbRisqueEleve,
            'clickRate'             => $clickRate,
            'submissionRate'        => $submissionRate,
            'reportRate'            => $reportRate,
            'totalEnvoyes'          => $totalEnvoyes,
            'campagnesPhishing'     => $campagnesPhishing,
            'campagnesLabels'       => json_encode($campagnesLabels),
            'campagnesClics'        => json_encode($campagnesClics),
            'campagnesSubmissions'  => json_encode($campagnesSubs),
            'campagnesSignalements' => json_encode($campagnesSig),
            'departements'          => $deptStats,
            'topVulnerabilites'     => $topVulnerabilites,
            'formationsIndiv'       => $formationsIndiv,
            'topEmployes'           => $topEmployes,
            'alertes'               => $alertes,
            'activiteRecente'       => $activiteRecente,
        ]);
    }
}