<?php

namespace App\Controller\RSSI;

use App\Entity\CampagneFormation;
use App\Entity\Departement;
use App\Entity\Employe;
use App\Entity\ModuleFormation;
use App\Entity\ProgressionModule;
use App\Entity\RSSI;
use App\Service\EmailService;
use App\Service\EmailTemplateService;
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
    private function getRssi(): RSSI { return $this->getUser(); }
    private function getEntreprise(): ?\App\Entity\Entreprise { return $this->getRssi()->getEntreprise(); }

    // ══════════════════════════════════════════
    // LISTE
    // ══════════════════════════════════════════
    #[Route('', name: 'rssi_formations_liste')]
    public function liste(EntityManagerInterface $em): Response
    {
        $campagnes = $em->getRepository(CampagneFormation::class)
            ->findBy(['rssi' => $this->getRssi()], ['dateCreation' => 'DESC']);

        foreach ($campagnes as $c) {
            $this->mettreAJourStatut($c);
        }
        $em->flush();

        return $this->render('rssi/formations/liste.html.twig', ['campagnes' => $campagnes]);
    }

    // ══════════════════════════════════════════
    // NOUVELLE CAMPAGNE
    // ══════════════════════════════════════════
    #[Route('/nouvelle', name: 'rssi_formations_nouvelle', methods: ['GET', 'POST'])]
    public function nouvelle(Request $request, EntityManagerInterface $em, EmailService $emailService): Response
    {
        $entreprise   = $this->getEntreprise();
        $modules      = $em->getRepository(ModuleFormation::class)->findBy(['estPublie' => true], ['id' => 'ASC']);
        $departements = $entreprise
            ? $em->getRepository(Departement::class)->findBy(['entreprise' => $entreprise], ['nom' => 'ASC'])
            : [];

        if ($request->isMethod('POST')) {
            $titre          = trim($request->request->get('titre', ''));
            $description    = trim($request->request->get('description', ''));
            $trimestre      = $request->request->get('trimestre', 'T1');
            $annee          = (int) $request->request->get('annee', date('Y'));
            $dateFin        = $request->request->get('date_fin');
            $pointsPenalite = (int) $request->request->get('points_penalite', 50);
            $moduleIds      = $request->request->all('modules') ?: [];
            $deptIds        = $request->request->all('departements') ?: [];
            $tousEmployes   = $request->request->get('tous_employes') === '1';

            if (empty($titre) || empty($dateFin) || empty($moduleIds)) {
                $this->addFlash('error', 'Titre, date de fin et au moins un module sont obligatoires.');
                return $this->render('rssi/formations/nouvelle.html.twig', compact('modules', 'departements'));
            }

            $campagne = new CampagneFormation();
            $campagne->setTitre($titre)
                ->setDescription($description ?: null)
                ->setTrimestre($trimestre)
                ->setAnnee($annee)
                ->setDateFin(new \DateTime($dateFin))
                ->setPointsPenalite($pointsPenalite)
                ->setRssi($this->getRssi())
                ->setStatut('EN_COURS')
                ->setDateDebut(new \DateTime());

            foreach ($moduleIds as $mid) {
                $m = $em->getRepository(ModuleFormation::class)->find($mid);
                if ($m) $campagne->addModule($m);
            }
            $em->persist($campagne);

            $employes = $this->getEmployesCibles($em, $entreprise, $tousEmployes, $deptIds);
            $doublons = 0;
            $crees    = 0;

            foreach ($employes as $employe) {
                foreach ($moduleIds as $mid) {
                    $module = $em->getRepository(ModuleFormation::class)->find($mid);
                    if (!$module) continue;

                    $existant = $em->getRepository(ProgressionModule::class)->findOneBy([
                        'employe' => $employe,
                        'module'  => $module,
                    ]);

                    if ($existant && $existant->getStatut() !== 'TERMINE') {
                        $doublons++;
                        continue;
                    }

                    $prog = new ProgressionModule();
                    $prog->setEmploye($employe)
                        ->setModule($module)
                        ->setCampagne($campagne)
                        ->setTypeAttribution('CAMPAGNE')
                        ->setStatut('NON_COMMENCE')
                        ->setPourcentageProgression(0)
                        ->setDateDebut(null)
                        ->setDateTermine(null)
                        ->setDateDernierAcces(null);
                    $em->persist($prog);
                    $crees++;
                }
            }

            $em->flush();

            $this->recalculerStats($campagne);
            $em->flush();

            if ($doublons > 0) {
                $this->addFlash('warning', "{$doublons} module(s) non réattribué(s) : l'employé a déjà ce module en cours ou non commencé.");
            }
            if ($crees === 0 && $doublons > 0) {
                $this->addFlash('error', 'Aucune nouvelle progression créée. Tous les employés ciblés ont déjà ces modules en cours.');
                return $this->redirectToRoute('rssi_formations_liste');
            }

            $dateDebutFmt  = (new \DateTime())->format('d/m/Y');
            $dateFinFmt    = (new \DateTime($dateFin))->format('d/m/Y');
            $appUrl        = $_ENV['APP_URL'] ?? 'http://localhost:8000';
            $urlFormations = rtrim($appUrl, '/') . '/employe/formations';

            foreach ($employes as $employe) {
                $aUneProgression = $em->getRepository(ProgressionModule::class)->findOneBy([
                    'campagne' => $campagne,
                    'employe'  => $employe,
                ]);
                if (!$aUneProgression) continue;

                try {
                    $html = EmailTemplateService::nouvelleFormationAssignee(
                        prenom:              $employe->getPrenom(),
                        nom:                 $employe->getNom(),
                        campagneTitre:       $titre,
                        campagneDescription: $description ?: 'Campagne de sensibilisation à la cybersécurité.',
                        dateDebut:           $dateDebutFmt,
                        dateFin:             $dateFinFmt,
                        nbModules:           count($moduleIds),
                        pointsPenalite:      $pointsPenalite,
                        urlFormations:       $urlFormations
                    );
                    $emailService->envoyerEmailLeitime(
                        destinataire: $employe->getEmail(),
                        sujet:        "📚 Nouvelle formation assignée : {$titre}",
                        contenuHtml:  $html
                    );
                } catch (\Exception) {
                }
            }

            $this->addFlash('success', "Campagne « {$titre} » créée — {$crees} attribution(s) effectuée(s).");
            return $this->redirectToRoute('rssi_formations_liste');
        }

        return $this->render('rssi/formations/nouvelle.html.twig', [
            'modules'      => $modules,
            'departements' => $departements,
            'annee'        => (int) date('Y'),
        ]);
    }

    // ══════════════════════════════════════════
    // SUIVI FORMATIONS INDIVIDUELLES
    // ══════════════════════════════════════════
    #[Route('/individuelles', name: 'rssi_formations_individuelles')]
    public function individuelles(Request $request, EntityManagerInterface $em): Response
    {
        $entreprise = $this->getEntreprise();
        if (!$entreprise) {
            return $this->render('rssi/formations/individuelles.html.twig', [
                'progressions' => [],
                'modules'      => [],
            ]);
        }

        $employes = $em->createQueryBuilder()
            ->select('e')->from(Employe::class, 'e')
            ->join('e.departement', 'd')
            ->where('d.entreprise = :ent AND e.estActif = true')
            ->setParameter('ent', $entreprise)
            ->getQuery()->getResult();

        $employeIds = array_map(fn($e) => $e->getId(), $employes);

        if (empty($employeIds)) {
            return $this->render('rssi/formations/individuelles.html.twig', [
                'progressions' => [],
                'modules'      => [],
            ]);
        }

        $qb = $em->createQueryBuilder()
            ->select('p')->from(ProgressionModule::class, 'p')
            ->where('p.campagne IS NULL')
            ->andWhere('p.employe IN (:ids)')
            ->setParameter('ids', $employeIds)
            ->orderBy('p.dateDebut', 'DESC');

        $filtreStatut   = $request->query->get('statut');
        $filtreModuleId = $request->query->get('module_id');
        if ($filtreStatut)   { $qb->andWhere('p.statut = :s')->setParameter('s', $filtreStatut); }
        if ($filtreModuleId) { $qb->andWhere('p.module = :m')->setParameter('m', (int)$filtreModuleId); }

        $progressions = $qb->getQuery()->getResult();
        $modules      = $em->getRepository(ModuleFormation::class)->findBy(['estPublie' => true], ['titre' => 'ASC']);

        return $this->render('rssi/formations/individuelles.html.twig', [
            'progressions' => $progressions,
            'modules'      => $modules,
        ]);
    }

    // ══════════════════════════════════════════
    // RAPPEL INDIVIDUEL
    // ══════════════════════════════════════════
    #[Route('/individuelle/{id}/rappel', name: 'rssi_formations_rappel_individuel', methods: ['POST'])]
    public function rappelIndividuel(int $id, EntityManagerInterface $em, EmailService $emailService): Response
    {
        $progression = $em->getRepository(ProgressionModule::class)->find($id);
        if (!$progression) throw $this->createNotFoundException('Progression introuvable.');
        if ($progression->getEmploye()->getEntreprise()?->getId() !== $this->getEntreprise()?->getId()) {
            throw $this->createAccessDeniedException();
        }
        if ($progression->getStatut() === 'TERMINE') {
            $this->addFlash('info', 'Cette formation est déjà terminée.');
            return $this->redirectToRoute('rssi_formations_individuelles');
        }

        $employe = $progression->getEmploye();
        $module  = $progression->getModule();
        $appUrl  = $_ENV['APP_URL'] ?? 'http://localhost:8000';

        try {
            $html = EmailTemplateService::rappelFormationIndividuelle(
                prenom:       $employe->getPrenom(),
                nom:          $employe->getNom(),
                moduleTitre:  $module->getTitre(),
                progression:  $progression->getPourcentageProgression(),
                urlFormation: rtrim($appUrl, '/') . '/employe/formations/' . $module->getId(),
            );
            $emailService->envoyerEmailLeitime(
                destinataire: $employe->getEmail(),
                sujet:        "📚 Rappel : votre formation « {$module->getTitre()} »",
                contenuHtml:  $html
            );
            $this->addFlash('success', "Rappel envoyé à {$employe->getPrenom()} {$employe->getNom()}.");
        } catch (\Exception) {
            $this->addFlash('error', "Erreur lors de l'envoi du rappel.");
        }

        return $this->redirectToRoute('rssi_formations_individuelles');
    }

    // ══════════════════════════════════════════
    // DÉTAIL D'UNE CAMPAGNE
    // ══════════════════════════════════════════
    #[Route('/{id}', name: 'rssi_formations_detail', requirements: ['id' => '\d+'])]
    public function detail(CampagneFormation $campagne, EntityManagerInterface $em): Response
    {
        $this->verifierAcces($campagne);
        $this->recalculerStats($campagne);
        $em->flush();

        $progressions = $campagne->getProgressions();
        $nbModules    = $campagne->getModules()->count();

        $statsModules = [];
        foreach ($campagne->getModules() as $module) {
            $progsModule = $progressions->filter(fn($p) => $p->getModule()->getId() === $module->getId());
            $total       = $progsModule->count();
            $somme       = 0;
            foreach ($progsModule as $p) { $somme += $p->getPourcentageProgression(); }
            $statsModules[] = [
                'module'   => $module,
                'total'    => $total,
                'termines' => $progsModule->filter(fn($p) => $p->getStatut() === 'TERMINE')->count(),
                'enCours'  => $progsModule->filter(fn($p) => $p->getStatut() === 'EN_COURS')->count(),
                'pct'      => $total > 0 ? (int) round($somme / $total) : 0,
            ];
        }

        $parEmploye = [];
        foreach ($progressions as $p) {
            $eid = $p->getEmploye()->getId();
            if (!isset($parEmploye[$eid])) {
                $parEmploye[$eid] = [
                    'employe'      => $p->getEmploye(),
                    'progressions' => [],
                    'somme_pct'    => 0,
                    'nb_modules'   => 0,
                    'termines'     => 0,
                    'retard'       => false,
                    'pct_moyen'    => 0,
                    'tout_termine' => false,
                ];
            }
            $parEmploye[$eid]['progressions'][] = $p;
            $parEmploye[$eid]['nb_modules']++;
            $parEmploye[$eid]['somme_pct'] += $p->getPourcentageProgression();
            if ($p->getStatut() === 'TERMINE')  $parEmploye[$eid]['termines']++;
            if ($p->isEstEnRetard())             $parEmploye[$eid]['retard'] = true;
        }

        foreach ($parEmploye as &$data) {
            $data['pct_moyen']    = $data['nb_modules'] > 0 ? (int) round($data['somme_pct'] / $data['nb_modules']) : 0;
            $data['tout_termine'] = ($data['termines'] === $nbModules);
        }
        unset($data);

        $totalP    = $campagne->getTotalParticipants();
        $termines  = $campagne->getNombreTermines();
        $pctGlobal = $totalP > 0 ? (int) round(($termines / $totalP) * 100) : 0;

        return $this->render('rssi/formations/detail.html.twig', [
            'campagne'     => $campagne,
            'statsModules' => $statsModules,
            'parEmploye'   => $parEmploye,
            'pctGlobal'    => $pctGlobal,
            'totalP'       => $totalP,
            'nbTermines'   => $termines,
            'nbEnCours'    => $campagne->getNombreEnCours(),
            'nbEnRetard'   => $campagne->getNombreEnRetard(),
        ]);
    }

    // ══════════════════════════════════════════
    // CHANGER STATUT
    // ══════════════════════════════════════════
    #[Route('/{id}/statut/{statut}', name: 'rssi_formations_statut', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function changerStatut(CampagneFormation $campagne, string $statut, EntityManagerInterface $em): Response
    {
        $this->verifierAcces($campagne);
        if (!in_array($statut, ['EN_COURS', 'TERMINEE', 'ANNULEE'])) {
            $this->addFlash('error', 'Statut invalide.');
            return $this->redirectToRoute('rssi_formations_detail', ['id' => $campagne->getId()]);
        }

        if ($statut === 'EN_COURS' && !$campagne->getDateDebut()) {
            $campagne->setDateDebut(new \DateTime());
        }

        $campagne->setStatut($statut);
        $em->flush();
        $this->addFlash('success', 'Statut mis à jour.');
        return $this->redirectToRoute('rssi_formations_detail', ['id' => $campagne->getId()]);
    }

    // ══════════════════════════════════════════
    // SUPPRIMER
    // ══════════════════════════════════════════
    #[Route('/{id}/supprimer', name: 'rssi_formations_supprimer', methods: ['POST'], requirements: ['id' => '\d+'])]
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

    // ══════════════════════════════════════════
    // HELPERS PRIVÉS
    // ══════════════════════════════════════════

    private function verifierAcces(CampagneFormation $campagne): void
    {
        if ($campagne->getRssi() !== $this->getRssi()) {
            throw $this->createAccessDeniedException();
        }
    }

    /**
     * Recalcule les stats SANS toucher au statut.
     */
    private function recalculerStats(CampagneFormation $campagne): void
    {
        $progressions = $campagne->getProgressions();
        $nbModules    = $campagne->getModules()->count();

        $parEmploye = [];
        foreach ($progressions as $p) {
            $eid = $p->getEmploye()->getId();
            $parEmploye[$eid]['nb_modules'] = ($parEmploye[$eid]['nb_modules'] ?? 0) + 1;
            $parEmploye[$eid]['termines']   = ($parEmploye[$eid]['termines']   ?? 0)
                + ($p->getStatut() === 'TERMINE' ? 1 : 0);
        }

        $nbTotalEmployes = count($parEmploye);
        $nbTermines      = 0;
        $nbEnCours       = 0;

        foreach ($parEmploye as $data) {
            if ($data['termines'] === $nbModules && $nbModules > 0) {
                $nbTermines++;
            } else {
                $nbEnCours++;
            }
        }

        $nbEnRetard = $progressions->filter(fn($p) => $p->isEstEnRetard())->count();

        $campagne->setTotalParticipants($nbTotalEmployes);
        $campagne->setNombreTermines($nbTermines);
        $campagne->setNombreEnCours($nbEnCours);
        $campagne->setNombreEnRetard($nbEnRetard);
    }

    /**
     * Met à jour le statut + recalcul des stats.
     * Le statut passe EN_COURS → TERMINEE uniquement si la date de fin est dépassée.
     */
    private function mettreAJourStatut(CampagneFormation $campagne): void
    {
        $statutActuel = $campagne->getStatut();
        $now = new \DateTime();
        $fin = $campagne->getDateFin();

        // ✅ Transition auto UNIQUEMENT si date de fin dépassée
        if ($statutActuel === 'EN_COURS' && $fin && $now > $fin) {
            $campagne->setStatut('TERMINEE');
        }

        $this->recalculerStats($campagne);
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