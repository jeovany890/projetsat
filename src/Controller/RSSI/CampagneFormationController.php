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
            $campagne->setTitre($titre)
                ->setDescription($description ?: null)
                ->setTrimestre($trimestre)
                ->setAnnee($annee)
                ->setDateDebut(new \DateTime($dateDebut))
                ->setDateFin(new \DateTime($dateFin))
                ->setPointsPenalite($pointsPenalite)
                ->setRssi($this->getRssi())
                ->setStatut('PLANIFIEE');

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

                    // ── RÈGLE D'ATTRIBUTION ──────────────────────────────────
                    // On cherche UNE PROGRESSION EXISTANTE pour cet employé+module
                    // peu importe la campagne ou le type d'attribution.
                    $existant = $em->getRepository(ProgressionModule::class)->findOneBy([
                        'employe' => $employe,
                        'module'  => $module,
                    ]);

                    // CAS 1 — Aucune progression → on crée normalement
                    // CAS 2 — Progression TERMINÉE → on peut recréer (nouvelle campagne)
                    // CAS 3 — Progression NON_COMMENCE ou EN_COURS → on ne touche pas
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

            // NE PAS fixer totalParticipants ici — il est calculé
            // dynamiquement par mettreAJourStatut() à partir des progressions réelles
            $em->flush();

            // Recalcul immédiat après flush pour avoir les bons chiffres
            $this->mettreAJourStatut($campagne);
            $em->flush();

            if ($doublons > 0) {
                $this->addFlash('warning', "{$doublons} module(s) non réattribué(s) : l'employé a déjà ce module en cours ou non commencé.");
            }
            if ($crees === 0 && $doublons > 0) {
                $this->addFlash('error', 'Aucune nouvelle progression créée. Tous les employés ciblés ont déjà ces modules en cours.');
                return $this->redirectToRoute('rssi_formations_liste');
            }

            // ── Emails aux employés ayant reçu au moins un module ──
            $dateDebutFmt  = (new \DateTime($dateDebut))->format('d/m/Y');
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
                    // Échec email silencieux — non bloquant
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
    // SUIVI FORMATIONS INDIVIDUELLES (phishing auto)
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

        // Formations individuelles = campagne IS NULL (attribuées automatiquement par phishing)
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
        $this->mettreAJourStatut($campagne);
        $em->flush();

        $progressions = $campagne->getProgressions();
        $nbModules    = $campagne->getModules()->count();

        // ── Stats par module ──────────────────────────────────────
        $statsModules = [];
        foreach ($campagne->getModules() as $module) {
            $progsModule = $progressions->filter(fn($p) => $p->getModule()->getId() === $module->getId());
            $total       = $progsModule->count();
            $somme       = 0;
            foreach ($progsModule as $p) { $somme += $p->getPourcentageProgression(); }
            $statsModules[] = [
                'module'   => $module,
                'total'    => $total,   // nb employés sur ce module
                'termines' => $progsModule->filter(fn($p) => $p->getStatut() === 'TERMINE')->count(),
                'enCours'  => $progsModule->filter(fn($p) => $p->getStatut() === 'EN_COURS')->count(),
                'pct'      => $total > 0 ? (int) round($somme / $total) : 0,
            ];
        }

        // ── Stats par employé (vue consolidée) ───────────────────
        // Un employé peut avoir plusieurs progressions dans une campagne
        // (une par module). On consolide ici pour avoir une ligne par employé.
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
            $data['pct_moyen']    = $data['nb_modules'] > 0
                ? (int) round($data['somme_pct'] / $data['nb_modules'])
                : 0;
            // Tout terminé = tous ses modules dans cette campagne sont à TERMINE
            $data['tout_termine'] = ($data['termines'] === $nbModules);
        }
        unset($data);

        // totalP et termines viennent de mettreAJourStatut() — basés sur employés distincts
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
        if (!in_array($statut, ['PLANIFIEE', 'EN_COURS', 'TERMINEE', 'ANNULEE'])) {
            $this->addFlash('error', 'Statut invalide.');
            return $this->redirectToRoute('rssi_formations_detail', ['id' => $campagne->getId()]);
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
     * Recalcule dynamiquement les compteurs d'une campagne
     * à partir des progressions réelles en base.
     *
     * LOGIQUE :
     * - totalParticipants = nb d'employés DISTINCTS ayant au moins une progression dans la campagne
     * - nombreTermines    = nb d'employés DISTINCTS ayant TOUS leurs modules à TERMINE
     * - nombreEnCours     = nb d'employés DISTINCTS ayant au moins 1 module EN_COURS ou NON_COMMENCE
     * - nombreEnRetard    = nb de progressions individuelles en retard (écheance dépassée, non terminée)
     */
    private function mettreAJourStatut(CampagneFormation $campagne): void
    {
        $statutActuel = $campagne->getStatut();

        // ── Règles de transition automatique par date ──────────────
        //
        // On ne touche au statut QUE dans deux cas précis :
        //   1. La campagne est PLANIFIEE et la date de début est passée
        //      → on la passe automatiquement EN_COURS
        //   2. La campagne est EN_COURS et la date de fin est dépassée
        //      → on la passe automatiquement TERMINEE
        //
        // On ne touche JAMAIS au statut si :
        //   - Le RSSI l'a manuellement passée EN_COURS (bouton Lancer)
        //   - Le RSSI l'a manuellement passée TERMINEE (bouton Terminer)
        //   - Le RSSI l'a ANNULEE
        //
        $now = new \DateTime();
        $fin = $campagne->getDateFin();

        if ($statutActuel === 'PLANIFIEE') {
            // Seule transition auto depuis PLANIFIEE :
            // si la date de début est passée → EN_COURS automatiquement
            $debut = $campagne->getDateDebut();
            if (!$debut || $now >= $debut) {
                $campagne->setStatut('EN_COURS');
            }
            // Sinon on reste PLANIFIEE — le RSSI doit cliquer Lancer
        } elseif ($statutActuel === 'EN_COURS' && $fin && $now > $fin) {
            // Date de fin dépassée → TERMINEE automatiquement
            $campagne->setStatut('TERMINEE');
        }
        // TERMINEE, ANNULEE, ou EN_COURS sans date de fin dépassée → on ne change rien

        $progressions = $campagne->getProgressions();
        $nbModules    = $campagne->getModules()->count();

        // ── Regrouper les progressions par employé ──
        $parEmploye = [];
        foreach ($progressions as $p) {
            $eid = $p->getEmploye()->getId();
            $parEmploye[$eid]['nb_modules'] = ($parEmploye[$eid]['nb_modules'] ?? 0) + 1;
            $parEmploye[$eid]['termines']   = ($parEmploye[$eid]['termines']   ?? 0)
                + ($p->getStatut() === 'TERMINE' ? 1 : 0);
        }

        // ── Calcul des compteurs par employé distinct ──
        $nbTotalEmployes = count($parEmploye);  // employés distincts dans la campagne
        $nbTermines      = 0;
        $nbEnCours       = 0;

        foreach ($parEmploye as $data) {
            if ($data['termines'] === $nbModules && $nbModules > 0) {
                // Tous les modules de la campagne sont terminés pour cet employé
                $nbTermines++;
            } else {
                // Au moins un module pas encore terminé
                $nbEnCours++;
            }
        }

        // ── Retard : progressions non terminées avec écheance dépassée ──
        $nbEnRetard = $progressions->filter(fn($p) => $p->isEstEnRetard())->count();

        // ── Mise à jour de la campagne ──
        $campagne->setTotalParticipants($nbTotalEmployes);
        $campagne->setNombreTermines($nbTermines);
        $campagne->setNombreEnCours($nbEnCours);
        $campagne->setNombreEnRetard($nbEnRetard);
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