<?php

namespace App\Controller\RSSI;

use App\Entity\CampagneFormation;
use App\Entity\CampagnePhishing;
use App\Entity\Employe;
use App\Entity\RSSI;
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
    public function index(EntityManagerInterface $em): Response
    {
        /** @var RSSI $rssi */
        $rssi = $this->getUser();
        $entreprise = $rssi->getEntreprise();

        // Guard : RSSI sans entreprise liée (compte créé avant le fix)
        if (!$entreprise) {
            return $this->render('rssi/dashboard.html.twig', [
                'totalEmployes'            => 0,
                'totalDepartements'        => 0,
                'scoreMoyen'               => 0,
                'campagnesPhishingActives' => 0,
                'tauxClicMoyen'            => 0,
                'derniereCampagne'         => null,
                'campagnesFormation'       => [],
                'employesVulnerables'      => [],
                'evolutionLabels'          => [],
                'evolutionScores'          => [],
            ]);
        }

        // Employés via départements
        $employes = $em->createQueryBuilder()
            ->select('e')
            ->from(Employe::class, 'e')
            ->join('e.departement', 'd')
            ->where('d.entreprise = :entreprise')
            ->setParameter('entreprise', $entreprise)
            ->getQuery()->getResult();

        $totalEmployes = count($employes);

        $totalDepartements = (int) $em->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(\App\Entity\Departement::class, 'd')
            ->where('d.entreprise = :entreprise')
            ->setParameter('entreprise', $entreprise)
            ->getQuery()->getSingleScalarResult();

        // Score vigilance moyen
        $scoreMoyen = $totalEmployes > 0
            ? round(array_sum(array_map(fn($e) => $e->getScoreVigilance(), $employes)) / $totalEmployes, 1)
            : 0;

        // Campagnes phishing actives
        $campagnesPhishingActives = (int) $em->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(CampagnePhishing::class, 'c')
            ->where('c.rssi = :rssi AND c.statut = :statut')
            ->setParameter('rssi', $rssi)
            ->setParameter('statut', 'EN_COURS')
            ->getQuery()->getSingleScalarResult();

        // Taux de clic moyen
        $campagnesPhishing = $em->createQueryBuilder()
            ->select('c')->from(CampagnePhishing::class, 'c')
            ->where('c.rssi = :rssi')->setParameter('rssi', $rssi)
            ->getQuery()->getResult();

        $tauxClicMoyen = 0;
        if (count($campagnesPhishing) > 0) {
            $totalClics   = array_sum(array_map(fn($c) => $c->getLiensCliques(), $campagnesPhishing));
            $totalEnvoyes = array_sum(array_map(fn($c) => $c->getEmailsEnvoyes(), $campagnesPhishing));
            $tauxClicMoyen = $totalEnvoyes > 0 ? round(($totalClics / $totalEnvoyes) * 100, 1) : 0;
        }

        // Dernière campagne phishing
        $derniereCampagne = $em->createQueryBuilder()
            ->select('c')->from(CampagnePhishing::class, 'c')
            ->where('c.rssi = :rssi')->setParameter('rssi', $rssi)
            ->orderBy('c.id', 'DESC')->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();

        // Campagnes formation en cours
        $campagnesFormation = $em->createQueryBuilder()
            ->select('c')->from(CampagneFormation::class, 'c')
            ->where('c.rssi = :rssi AND c.statut = :statut')
            ->setParameter('rssi', $rssi)->setParameter('statut', 'EN_COURS')
            ->orderBy('c.dateDebut', 'DESC')
            ->getQuery()->getResult();

        // Employés les plus vulnérables (score < 60)
        $employesVulnerables = array_filter($employes, fn($e) => $e->getScoreVigilance() < 60);
        usort($employesVulnerables, fn($a, $b) => $a->getScoreVigilance() <=> $b->getScoreVigilance());
        $employesVulnerables = array_slice($employesVulnerables, 0, 5);

        // Évolution score (6 derniers mois)
        $evolutionLabels = [];
        $evolutionScores = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = new \DateTime("-{$i} months");
            $evolutionLabels[] = $date->format('M Y');
            $evolutionScores[] = $scoreMoyen;
        }

        return $this->render('rssi/dashboard.html.twig', [
            'totalEmployes'            => $totalEmployes,
            'totalDepartements'        => $totalDepartements,
            'scoreMoyen'               => $scoreMoyen,
            'campagnesPhishingActives' => $campagnesPhishingActives,
            'tauxClicMoyen'            => $tauxClicMoyen,
            'derniereCampagne'         => $derniereCampagne,
            'campagnesFormation'       => $campagnesFormation,
            'employesVulnerables'      => $employesVulnerables,
            'evolutionLabels'          => $evolutionLabels,
            'evolutionScores'          => $evolutionScores,
        ]);
    }
}