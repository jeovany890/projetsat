<?php

namespace App\Controller\RSSI;

use App\Entity\CampagnePhishing;
use App\Entity\Departement;
use App\Entity\Employe;
use App\Entity\ModuleFormation;
use App\Entity\ProgressionModule;
use App\Entity\ResultatPhishing;
use App\Entity\RSSI;
use App\Repository\CampagnePhishingRepository;
use App\Service\RapportPdfService;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rssi/rapports')]
#[IsGranted('ROLE_RSSI')]
class RapportController extends AbstractController
{
    private function getRssi(): RSSI { return $this->getUser(); }

    #[Route('', name: 'rssi_rapports')]
    public function index(EntityManagerInterface $em): Response
    {
        $rssi       = $this->getRssi();
        $entreprise = $rssi->getEntreprise();

        $nbEmployes = $nbCampagnes = $nbFormations = 0;
        $employes   = [];

        if ($entreprise) {
            $employes = $em->createQueryBuilder()
                ->select('e')->from(Employe::class, 'e')
                ->join('e.departement', 'd')
                ->where('d.entreprise = :ent AND e.estActif = true')
                ->setParameter('ent', $entreprise)
                ->orderBy('e.nom', 'ASC')
                ->getQuery()->getResult();

            $nbEmployes  = count($employes);
            $nbCampagnes = count($em->getRepository(CampagnePhishing::class)->findBy(['rssi' => $rssi]));
            $nbFormations= count($em->getRepository(ModuleFormation::class)->findBy(['estPublie' => true]));
        }

        return $this->render('rssi/rapports/index.html.twig', [
            'entreprise'   => $entreprise,
            'nbEmployes'   => $nbEmployes,
            'nbCampagnes'  => $nbCampagnes,
            'nbFormations' => $nbFormations,
            'employes'     => $employes,
        ]);
    }

    #[Route('/entreprise/pdf', name: 'rssi_rapport_entreprise_pdf')]
    public function rapportEntreprisePdf(
        EntityManagerInterface $em,
        CampagnePhishingRepository $campagneRepo
    ): Response {
        $rssi       = $this->getRssi();
        $entreprise = $rssi->getEntreprise();

        if (!$entreprise) {
            $this->addFlash('error', 'Aucune entreprise associée à votre compte.');
            return $this->redirectToRoute('rssi_rapports');
        }

        // ── Employés ─────────────────────────────────────────────
        $employes = $em->createQueryBuilder()
            ->select('e')->from(Employe::class, 'e')
            ->join('e.departement', 'd')
            ->where('d.entreprise = :ent')
            ->setParameter('ent', $entreprise)
            ->orderBy('e.scoreVigilance', 'ASC') // trié par score pour top performers + risque
            ->getQuery()->getResult();

        $totalEmployes = count($employes);
        $totalActifs   = count(array_filter($employes, fn($e) => $e->isEstActif()));
        $scores        = array_map(fn($e) => $e->getScoreVigilance(), $employes);
        $scoreMoyen    = $totalEmployes > 0 ? round(array_sum($scores) / $totalEmployes, 1) : 0;

        $nbRisqueEleve  = count(array_filter($employes, fn($e) => $e->getScoreVigilance() < 35));
        $nbRisqueMoyen  = count(array_filter($employes, fn($e) => $e->getScoreVigilance() >= 35 && $e->getScoreVigilance() < 60));
        $nbRisqueFaible = count(array_filter($employes, fn($e) => $e->getScoreVigilance() >= 60));

        // ── Stats phishing globales ───────────────────────────────
        $employeIds = array_map(fn($e) => $e->getId(), $employes);
        $totalEnvoyes = $totalClics = $totalSoumissions = $totalSignalements = 0;

        if (!empty($employeIds)) {
            $r = $em->createQueryBuilder()
                ->select(
                    'SUM(CASE WHEN r.emailEnvoye = true THEN 1 ELSE 0 END) as env',
                    'SUM(CASE WHEN r.lienClique = true THEN 1 ELSE 0 END) as clics',
                    'SUM(CASE WHEN r.soumissionDonnees = true THEN 1 ELSE 0 END) as subs',
                    'SUM(CASE WHEN r.signale = true THEN 1 ELSE 0 END) as sigs'
                )
                ->from(ResultatPhishing::class, 'r')
                ->join('r.employe', 'e')
                ->where('e.id IN (:ids)')->setParameter('ids', $employeIds)
                ->getQuery()->getSingleResult();

            $totalEnvoyes      = (int)($r['env']  ?? 0);
            $totalClics        = (int)($r['clics'] ?? 0);
            $totalSoumissions  = (int)($r['subs']  ?? 0);
            $totalSignalements = (int)($r['sigs']  ?? 0);
        }

        $clickRate        = $totalEnvoyes > 0 ? round($totalClics       / $totalEnvoyes * 100, 1) : 0;
        $submissionRate   = $totalEnvoyes > 0 ? round($totalSoumissions  / $totalEnvoyes * 100, 1) : 0;
        $reportRate       = $totalEnvoyes > 0 ? round($totalSignalements / $totalEnvoyes * 100, 1) : 0;
        $resilienceFactor = $clickRate > 0    ? round($reportRate / $clickRate, 2) : 'N/A';

        // ── Campagnes ─────────────────────────────────────────────
        $campagnesPhishing = $campagneRepo->findBy(['rssi' => $rssi], ['id' => 'DESC']);
        $statsCampagnes    = [];
        foreach ($campagnesPhishing as $c) {
            $st  = $campagneRepo->getStats($c);
            $env = max($st['emailsEnvoyes'], 1);
            $statsCampagnes[] = [
                'titre'        => $c->getTitre(),
                'statut'       => $c->getStatut(),
                'envoyes'      => $st['emailsEnvoyes'],
                'clics'        => $st['liensCliques'],
                'soumissions'  => $st['soumissions']    ?? 0,
                'signalements' => $st['emailsSignales'],
                'clickRate'    => round($st['liensCliques']           / $env * 100, 1),
                'subRate'      => round(($st['soumissions']  ?? 0)    / $env * 100, 1),
                'sigRate'      => round($st['emailsSignales']          / $env * 100, 1),
            ];
        }

        // ── Départements ──────────────────────────────────────────
        $departements = $em->getRepository(Departement::class)->findBy(['entreprise' => $entreprise]);
        $statsDepts   = [];
        foreach ($departements as $dept) {
            $emps = $dept->getEmployes()->toArray();
            $nb   = count($emps);
            if ($nb === 0) continue;
            $scrs = array_map(fn($e) => $e->getScoreVigilance(), $emps);
            $statsDepts[] = [
                'nom'    => $dept->getNom(),
                'nb'     => $nb,
                'score'  => round(array_sum($scrs) / $nb, 1),
                'risque' => count(array_filter($emps, fn($e) => $e->getScoreVigilance() < 35)),
            ];
        }
        usort($statsDepts, fn($a, $b) => $a['score'] <=> $b['score']);

        // ── Formations ────────────────────────────────────────────
        $formationsIndiv     = 0;
        $formationsTerminees = 0;
        if (!empty($employeIds)) {
            $formationsIndiv = (int) $em->createQueryBuilder()
                ->select('COUNT(p.id)')->from(ProgressionModule::class, 'p')
                ->where('p.campagne IS NULL')->andWhere('p.employe IN (:ids)')
                ->setParameter('ids', $employeIds)->getQuery()->getSingleScalarResult();

            $formationsTerminees = (int) $em->createQueryBuilder()
                ->select('COUNT(p.id)')->from(ProgressionModule::class, 'p')
                ->where('p.statut = :done')->andWhere('p.employe IN (:ids)')
                ->setParameter('done', 'TERMINE')->setParameter('ids', $employeIds)
                ->getQuery()->getSingleScalarResult();
        }

        // ── Génération PDF ────────────────────────────────────────
        $html = $this->renderView('rssi/rapports/pdf_entreprise.html.twig', [
            'entreprise'          => $entreprise,
            'employes'            => $employes,    // ← NOUVEAU : nécessaire pour top/risque
            'totalEmployes'       => $totalEmployes,
            'totalActifs'         => $totalActifs,
            'scoreMoyen'          => $scoreMoyen,
            'nbRisqueEleve'       => $nbRisqueEleve,
            'nbRisqueMoyen'       => $nbRisqueMoyen,
            'nbRisqueFaible'      => $nbRisqueFaible,
            'totalEnvoyes'        => $totalEnvoyes,
            'totalClics'          => $totalClics,
            'totalSoumissions'    => $totalSoumissions,
            'totalSignalements'   => $totalSignalements,
            'clickRate'           => $clickRate,
            'submissionRate'      => $submissionRate,
            'reportRate'          => $reportRate,
            'resilienceFactor'    => $resilienceFactor,
            'statsCampagnes'      => $statsCampagnes,
            'statsDepts'          => $statsDepts,
            'formationsIndiv'     => $formationsIndiv,
            'formationsTerminees' => $formationsTerminees,
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('chroot', $this->getParameter('kernel.project_dir') . '/public');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'rapport-cyber-' . strtolower(str_replace(' ', '-', $entreprise->getNom())) . '-' . date('Y-m-d') . '.pdf';

        return new Response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    #[Route('/employe/{id}/pdf', name: 'rssi_rapport_employe_pdf', requirements: ['id' => '\d+'])]
    public function rapportEmployePdf(
        int $id,
        EntityManagerInterface $em,
        RapportPdfService $rapportPdfService
    ): Response {
        $employe = $em->getRepository(Employe::class)->find($id);
        if (!$employe) throw $this->createNotFoundException();
        if ($employe->getEntreprise()?->getId() !== $this->getRssi()->getEntreprise()?->getId()) {
            throw $this->createAccessDeniedException();
        }

        $resultats     = $em->createQueryBuilder()
            ->select('r')->from(ResultatPhishing::class, 'r')
            ->where('r.employe = :e')->setParameter('e', $employe)
            ->orderBy('r.dateClic', 'DESC')->getQuery()->getResult();

        $nbEnvoyes      = count(array_filter($resultats, fn($r) => $r->isEmailEnvoye()));
        $nbClics        = count(array_filter($resultats, fn($r) => $r->isLienClique()));
        $nbSoumissions  = count(array_filter($resultats, fn($r) => $r->isSoumissionDonnees()));
        $nbSignalements = count(array_filter($resultats, fn($r) => $r->isSignale()));

        $clickRate      = $nbEnvoyes > 0 ? round($nbClics       / $nbEnvoyes * 100, 1) : 0;
        $submissionRate = $nbEnvoyes > 0 ? round($nbSoumissions  / $nbEnvoyes * 100, 1) : 0;
        $reportRate     = $nbEnvoyes > 0 ? round($nbSignalements / $nbEnvoyes * 100, 1) : 0;

        $progressions        = $em->getRepository(ProgressionModule::class)->findBy(['employe' => $employe], ['dateDebut' => 'DESC']);
        $formationsTerminees = count(array_filter($progressions, fn($p) => $p->getStatut() === 'TERMINE'));
        $formationsEnCours   = count(array_filter($progressions, fn($p) => $p->getStatut() === 'EN_COURS'));

        return $rapportPdfService->generer([
            'employe'             => $employe,
            'nbEnvoyes'           => $nbEnvoyes,
            'nbClics'             => $nbClics,
            'nbSoumissions'       => $nbSoumissions,
            'nbSignalements'      => $nbSignalements,
            'clickRate'           => $clickRate,
            'submissionRate'      => $submissionRate,
            'reportRate'          => $reportRate,
            'progressions'        => $progressions,
            'formationsTerminees' => $formationsTerminees,
            'formationsEnCours'   => $formationsEnCours,
            'scoreVigilance'      => $employe->getScoreVigilance(),
            'niveauRisque'        => $employe->getNiveauVigilance(),
            'totalPoints'         => $employe->getTotalPoints(),
            'dateRapport'         => (new \DateTime())->format('d/m/Y à H:i'),
        ]);
    }
}