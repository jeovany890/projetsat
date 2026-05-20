<?php

namespace App\Controller\Employe;

use App\Entity\Employe;
use App\Entity\ProgressionModule;
use App\Entity\ModuleFormation;
use App\Entity\Chapitre;
use App\Entity\Quiz;
use App\Entity\TentativeQuiz;
use App\Entity\SimulationInteractive;
use App\Entity\ResultatSimulation;
use App\Service\ScoringMoteurService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/employe')]
#[IsGranted('ROLE_EMPLOYE')]
class EmployeController extends AbstractController
{
    public function __construct(private ScoringMoteurService $scoring) {}

    private function getEmploye(): Employe
    {
        /** @var Employe $e */
        $e = $this->getUser();
        return $e;
    }

    // ══════════════════════════════════════════
    // DASHBOARD
    // ══════════════════════════════════════════
    #[Route('', name: 'employe_dashboard')]
    public function dashboard(EntityManagerInterface $em): Response
    {
        $employe = $this->getEmploye();

        $progressions = $em->getRepository(ProgressionModule::class)->findBy(
            ['employe' => $employe],
            ['dateDernierAcces' => 'DESC']
        );

        $total    = count($progressions);
        $termines = count(array_filter($progressions, fn($p) => $p->getStatut() === 'TERMINE'));
        $enCours  = count(array_filter($progressions, fn($p) => $p->getStatut() === 'EN_COURS'));
        $enRetard = count(array_filter($progressions, fn($p) => $p->isEstEnRetard()));

        $enCoursSlice = array_slice(
            array_values(array_filter($progressions, fn($p) => $p->getStatut() !== 'TERMINE')),
            0, 3
        );

        $derniersResultats = $em->getRepository(ResultatSimulation::class)->findBy(
            ['employe' => $employe],
            ['dateTermine' => 'DESC'],
            3
        );

        return $this->render('employe/dashboard.html.twig', [
            'progressions'      => $enCoursSlice,
            'derniersResultats' => $derniersResultats,
            'statsFormations'   => [
                'total'     => $total,
                'terminees' => $termines,
                'enCours'   => $enCours,
                'enRetard'  => $enRetard,
            ],
        ]);
    }

    // ══════════════════════════════════════════
    // LISTE FORMATIONS
    // ══════════════════════════════════════════
    #[Route('/formations', name: 'employe_formations')]
    public function formations(EntityManagerInterface $em): Response
    {
        $progressions = $em->getRepository(ProgressionModule::class)->findBy(
            ['employe' => $this->getEmploye()],
            ['dateDernierAcces' => 'DESC']
        );

        return $this->render('employe/formations/liste.html.twig', [
            'progressions' => $progressions,
        ]);
    }

    // ══════════════════════════════════════════
    // COMMENCER UNE FORMATION
    // Cas 1 : campagne normale  → refuser si module déjà actif via phishing
    // Cas 2 : phishing détecté → reprendre la progression phishing existante
    // Cas 3 : reprise volontaire → créer une nouvelle progression (REPRISE)
    // ══════════════════════════════════════════
    #[Route('/formations/{id}/commencer', name: 'employe_formation_commencer', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function commencerFormation(ModuleFormation $module, EntityManagerInterface $em): Response
    {
        $employe = $this->getEmploye();

        // ── Cas 1 : une progression phishing active existe déjà ──────────
        // Le RSSI ne peut pas lancer une campagne normale par-dessus.
        // On redirige l'employé vers la progression phishing en cours.
        $progressionPhishingActive = $em->getRepository(ProgressionModule::class)->findOneBy([
            'employe'         => $employe,
            'module'          => $module,
            'typeAttribution' => 'PHISHING',
            'dateTermine'     => null,
        ]);

        if ($progressionPhishingActive) {
            $this->addFlash('warning',
                'Cette formation a déjà été attribuée suite à une campagne phishing. Veuillez la reprendre.');
            return $this->redirectToRoute('employe_formation_detail', [
                'id' => $module->getId(),
            ]);
        }

        // ── Cas 2 / Cas 3 : chercher une progression active (non terminée) ──
        $progressionActive = $em->getRepository(ProgressionModule::class)->findOneBy([
            'employe'     => $employe,
            'module'      => $module,
            'dateTermine' => null,
        ]);

        if ($progressionActive) {
            // Cas 2 : progression existante non terminée → reprendre, ne pas recréer
            if ($progressionActive->getStatut() === 'NON_COMMENCE') {
                $progressionActive->setStatut('EN_COURS')
                    ->setDateDebut(new \DateTime());
            }
            $progressionActive->setDateDernierAcces(new \DateTime());
            $em->flush();

            return $this->redirectToRoute('employe_formation_detail', [
                'id' => $module->getId(),
            ]);
        }

        // ── Cas 3 : aucune progression active → reprise volontaire ──────────
        // On relie la nouvelle progression à la dernière terminée (parent).
        $dernierTermine = $em->getRepository(ProgressionModule::class)->findOneBy(
            ['employe' => $employe, 'module' => $module],
            ['id' => 'DESC']
        );

        $progression = new ProgressionModule();
        $progression->setEmploye($employe)
            ->setModule($module)
            ->setTypeAttribution('REPRISE')
            ->setStatut('EN_COURS')
            ->setPourcentageProgression(0)
            ->setDateDebut(new \DateTime())
            ->setDateTermine(null)
            ->setDateDernierAcces(new \DateTime());

        $em->persist($progression);
        $em->flush();

        return $this->redirectToRoute('employe_formation_detail', [
            'id' => $module->getId(),
        ]);
    }
    // ══════════════════════════════════════════
    // DÉTAIL D'UNE FORMATION
    // ══════════════════════════════════════════
    #[Route('/formations/{id}', name: 'employe_formation_detail', requirements: ['id' => '\d+'])]
    #[Route('/formations/{id}/chapitre/{chapitreActifId}', name: 'employe_formation_chapitre', requirements: ['id' => '\d+', 'chapitreActifId' => '\d+'])]
    public function formationDetail(ModuleFormation $module, EntityManagerInterface $em, Request $request, int $chapitreActifId = 0): Response
    {
        $employe     = $this->getEmploye();
        $campagneId  = $request->query->get('campagne');
        $progression = null;

        // ── 1. Priorité : progression liée à une campagne spécifique ──────
        if ($campagneId) {
            $campagne    = $em->getRepository(\App\Entity\CampagneFormation::class)->find($campagneId);
            $progression = $campagne
                ? $em->getRepository(ProgressionModule::class)->findOneBy([
                    'employe'  => $employe,
                    'module'   => $module,
                    'campagne' => $campagne,
                ])
                : null;
        }

        // ── 2. Fallback : progression active non terminée (toutes sources) ──
        if (!$progression) {
            $progressions = $em->getRepository(ProgressionModule::class)->findBy(
                ['employe' => $employe, 'module' => $module],
                ['id' => 'DESC']
            );

            foreach ($progressions as $p) {
                if ($p->getStatut() !== 'TERMINE') {
                    $progression = $p;
                    break;
                }
            }

            // Si tout est terminé, afficher la dernière (lecture seule)
            if (!$progression && !empty($progressions)) {
                $progression = $progressions[0];
            }
        }

        // ── 3. Aucune progression → rediriger vers /commencer ────────────
        // On ne crée plus de progression "fantôme" ici.
        // L'employé doit passer par le bouton "Commencer" pour initialiser sa session.
        if (!$progression) {
            $this->addFlash('info', 'Veuillez cliquer sur "Commencer" pour démarrer cette formation.');
            return $this->redirectToRoute('employe_formations');
        }

        // ── 4. Mettre à jour le statut et la date d'accès ─────────────────
        if ($progression->getStatut() === 'NON_COMMENCE') {
            $progression->setStatut('EN_COURS')->setDateDebut(new \DateTime());
        }
        $progression->setDateDernierAcces(new \DateTime());
        $em->flush();

        // ── 5. Charger les tentatives FILTRÉES par la progression active ──
        // Correction bug critique : on ne retourne plus les anciennes tentatives
        // d'une session précédente. Le filtre par progression_id garantit
        // que les quiz semblent vierges à chaque reprise.
        $chapitres = $module->getChapitres()->toArray();
        usort($chapitres, fn($a, $b) => $a->getId() <=> $b->getId());

        $tentativesChapitres = [];
        foreach ($chapitres as $chapitre) {
            if ($chapitre->getQuiz()) {
                $tentative = $em->getRepository(TentativeQuiz::class)->findOneBy([
                    'employe'     => $employe,
                    'quiz'        => $chapitre->getQuiz(),
                    'progression' => $progression,   // ← FILTRE PAR SESSION
                ]);
                $tentativesChapitres[$chapitre->getId()] = $tentative;
            }
        }

        $tousChapitresTermines = $this->tousQuizChapitresReussis($chapitres, $tentativesChapitres);

        // ── 6. Résultat simulation filtré par progression active ───────────
        // Correction bug critique : on ne retourne plus l'ancien résultat
        // d'une simulation jouée lors d'une session précédente.
        $resultatSimulation = null;
        if ($module->getSimulation()) {
            $resultatSimulation = $em->getRepository(ResultatSimulation::class)->findOneBy([
                'employe'     => $employe,
                'simulation'  => $module->getSimulation(),
                'progression' => $progression,       // ← FILTRE PAR SESSION
            ]);
        }

        // ── 7. Déterminer le chapitre actif (premier non validé) ─────────
        $chapitreActifObj = null;
        if ($chapitreActifId > 0) {
            foreach ($chapitres as $chap) {
                if ($chap->getId() === $chapitreActifId) { $chapitreActifObj = $chap; break; }
            }
        }
        if (!$chapitreActifObj) {
            foreach ($chapitres as $chap) {
                $t    = $tentativesChapitres[$chap->getId()] ?? null;
                $done = !$chap->getQuiz() || ($t && $t->isAReussi());
                if (!$done) { $chapitreActifObj = $chap; break; }
            }
        }
        if (!$chapitreActifObj && !empty($chapitres)) {
            $chapitreActifObj = $chapitres[0];
        }

        return $this->render('employe/formations/detail.html.twig', [
            'module'                => $module,
            'progression'           => $progression,
            'chapitres'             => $chapitres,
            'tentativesChapitres'   => $tentativesChapitres,
            'tousChapitresTermines' => $tousChapitresTermines,
            'resultatSimulation'    => $resultatSimulation,
            'chapitreActif'         => $chapitreActifObj,
        ]);
    }

    // ══════════════════════════════════════════
    // TERMINER UN CHAPITRE
    // ══════════════════════════════════════════
    #[Route('/formations/{moduleId}/chapitre/{chapitreId}/terminer', name: 'employe_chapitre_terminer', methods: ['POST'])]
    public function terminerChapitre(int $moduleId, int $chapitreId, EntityManagerInterface $em): Response
    {
        $employe  = $this->getEmploye();
        $module   = $em->getRepository(ModuleFormation::class)->find($moduleId);
        $chapitre = $em->getRepository(Chapitre::class)->find($chapitreId);

        if (!$module || !$chapitre) {
            throw $this->createNotFoundException();
        }

        $progression = $em->getRepository(ProgressionModule::class)->findOneBy([
            'employe'     => $employe,
            'module'      => $module,
            'dateTermine' => null,   // ← uniquement la session active
        ]);

        if ($progression) {
            $totalChapitres = count($module->getChapitres());
            if ($totalChapitres > 0) {
                $chapitres = $module->getChapitres()->toArray();
                usort($chapitres, fn($a, $b) => $a->getId() <=> $b->getId());
                $pos     = array_search($chapitre, $chapitres);
                $max     = $module->getSimulation() ? 70 : 90;
                $nouveau = (int)(($pos + 1) / $totalChapitres * $max);
                if ($nouveau > $progression->getPourcentageProgression()) {
                    $progression->setPourcentageProgression($nouveau);
                }
            }
            $progression->setDateDernierAcces(new \DateTime());
            $em->flush();
        }

        if ($chapitre->getQuiz()) {
            return $this->redirectToRoute('employe_chapitre_quiz', [
                'moduleId'   => $moduleId,
                'chapitreId' => $chapitreId,
            ]);
        }

        return $this->redirectToRoute('employe_formation_detail', ['id' => $moduleId]);
    }

    // ══════════════════════════════════════════
    // QUIZ D'UN CHAPITRE
    // Scoring via ScoringMoteurService :
    //   Réussi       → +3 vigilance + points pédagogiques configurés en DB
    //   Échoué       →  0 vigilance,  +0 points pédagogiques
    //   Module fini  → +5 vigilance, +bonus de fin de module configuré en DB
    // ══════════════════════════════════════════
    #[Route('/formations/{moduleId}/chapitre/{chapitreId}/quiz', name: 'employe_chapitre_quiz')]
    public function quizChapitre(int $moduleId, int $chapitreId, Request $request, EntityManagerInterface $em): Response
    {
        $employe  = $this->getEmploye();
        $module   = $em->getRepository(ModuleFormation::class)->find($moduleId);
        $chapitre = $em->getRepository(Chapitre::class)->find($chapitreId);

        if (!$module || !$chapitre || !$chapitre->getQuiz()) {
            throw $this->createNotFoundException();
        }

        $quiz      = $chapitre->getQuiz();
        $questions = $quiz->getQuestions() ?? [];

        // ── Récupérer la progression active pour filtrer les tentatives ──
        $progressionActive = $em->getRepository(ProgressionModule::class)->findOneBy([
            'employe'     => $employe,
            'module'      => $module,
            'dateTermine' => null,
        ]);

        // Correction bug critique : on filtre par la progression active,
        // pas par (employe + quiz) global → les anciennes tentatives sont ignorées.
        $tentativeExistante = $progressionActive
            ? $em->getRepository(TentativeQuiz::class)->findOneBy([
                'employe'     => $employe,
                'quiz'        => $quiz,
                'progression' => $progressionActive,
            ])
            : null;

        // ── Limite de tentatives (nombreTentativesMax stocké dans Quiz) ──────
        // Si l'employé a déjà réussi : inutile de recommencer, on bloque.
        // Si la limite est atteinte sans réussite : on bloque aussi.
        $numeroActuel   = $tentativeExistante?->getNumeroTentative() ?? 0;
        $dejaReussi     = $tentativeExistante?->isAReussi() ?? false;
        $limiteAtteinte = $dejaReussi || ($numeroActuel >= $quiz->getNombreTentativesMax());

        if ($limiteAtteinte && $request->isMethod('POST')) {
            $this->addFlash('warning', $dejaReussi
                ? 'Vous avez déjà réussi ce quiz.'
                : sprintf('Nombre maximum de tentatives atteint (%d/%d).', $numeroActuel, $quiz->getNombreTentativesMax())
            );
            return $this->redirectToRoute('employe_formation_detail', ['id' => $moduleId]);
        }

        if ($request->isMethod('POST')) {
            $reponses  = $request->request->all('reponses');
            $score     = 0;
            $total     = count($questions);
            $resultats = [];

            foreach ($questions as $index => $q) {
                $rep            = $reponses[$index] ?? null;
                $bonnesReponses = $q['reponses_correctes'] ?? [];
                $correct        = $rep !== null && in_array($rep, $bonnesReponses);
                if ($correct) $score++;
                $resultats[$index] = [
                    'correct'     => $correct,
                    'donnee'      => $rep,
                    'bonne'       => $bonnesReponses[0] ?? null,
                    'explication' => $q['explication'] ?? null,
                ];
            }

            $pourcentage = $total > 0 ? (int)(($score / $total) * 100) : 0;
            $reussi      = $pourcentage >= $quiz->getScoreMinimum();

            // Sauvegarder la tentative et la lier à la progression active
            $tentative = $tentativeExistante ?? new TentativeQuiz();
            $tentative->setEmploye($employe)->setQuiz($quiz)
                ->setScore($score)->setTotalQuestions($total)
                ->setReponsesCorrectes($score)->setAReussi($reussi)
                ->setNumeroTentative(($tentativeExistante?->getNumeroTentative() ?? 0) + 1)
                ->setReponses($resultats)
                ->setTempsPasseSecondes(0)
                ->setDateDebut(new \DateTime())->setDateTermine(new \DateTime())
                ->setProgression($progressionActive);   // ← LIEN SESSION
            $em->persist($tentative);

            // Scoring quiz
            if ($reussi) {
                $this->scoring->quizReussi($employe);   // +3 vigilance
                $employe->ajouterPoints($quiz->getPointsTotal());
            } else {
                $this->scoring->quizEchoue($employe);   // 0 vigilance
            }

            // Progression du module
            $progression = $progressionActive;  // déjà récupéré plus haut

            $chapitresList = $module->getChapitres()->toArray();
            usort($chapitresList, fn($a, $b) => $a->getId() <=> $b->getId());
            $nbChap = count($chapitresList);

            $currentIdx = null;
            foreach ($chapitresList as $idx => $chap) {
                if ($chap->getId() === $chapitre->getId()) { $currentIdx = $idx; break; }
            }
            $chapitresSuivant   = ($currentIdx !== null && isset($chapitresList[$currentIdx + 1]))
                ? $chapitresList[$currentIdx + 1] : null;
            $estDernierChapitre = ($currentIdx !== null && $currentIdx === $nbChap - 1);

            $chapitresValides = 0;
            foreach ($chapitresList as $chap) {
                if (!$chap->getQuiz()) { $chapitresValides++; continue; }
                if ($chap->getId() === $chapitre->getId()) {
                    if ($reussi) $chapitresValides++;
                } else {
                    $t = $em->getRepository(TentativeQuiz::class)->findOneBy([
                        'employe'     => $employe,
                        'quiz'        => $chap->getQuiz(),
                        'progression' => $progressionActive,  // ← FILTRE SESSION
                    ]);
                    if ($t && $t->isAReussi()) $chapitresValides++;
                }
            }

            $aSimulation = $module->getSimulation() !== null;
            $maxPct      = $aSimulation ? 90 : 100;
            $nouveauPct  = $nbChap > 0 ? (int)(($chapitresValides / $nbChap) * $maxPct) : 0;

            if ($progression) {
                if ($nouveauPct > $progression->getPourcentageProgression()) {
                    $progression->setPourcentageProgression($nouveauPct);
                }
                $progression->setDateDernierAcces(new \DateTime());
                if ($progression->getStatut() === 'NON_COMMENCE') {
                    $progression->setStatut('EN_COURS')->setDateDebut(new \DateTime());
                }

                // Tous les chapitres validés + pas de simulation → module TERMINÉ
                if ($chapitresValides === $nbChap && !$aSimulation) {
                    $progression->setStatut('TERMINE')
                        ->setPourcentageProgression(100)
                        ->setDateTermine(new \DateTime());

                    // Scoring formation terminée : +5 vigilance + module points
                    $this->scoring->formationTerminee($employe);
                    $employe->ajouterPoints($module->getPointsReussite());
                }
            }

            $em->flush();

            $tousTermines = ($chapitresValides === $nbChap);
            $simulation   = ($tousTermines && $estDernierChapitre && $aSimulation)
                ? $module->getSimulation() : null;

            return $this->render('employe/formations/quiz_resultat.html.twig', [
                'module'             => $module,
                'chapitre'           => $chapitre,
                'quiz'               => $quiz,
                'score'              => $score,
                'total'              => $total,
                'pourcentage'        => $pourcentage,
                'reussi'             => $reussi,
                'resultats'          => $resultats,
                'questions'          => $questions,
                'retour_url'         => $this->generateUrl('employe_formation_detail', ['id' => $moduleId]),
                'chapitresSuivant'   => $chapitresSuivant,
                'estDernierChapitre' => $estDernierChapitre,
                'tousTermines'       => $tousTermines,
                'simulation'         => $simulation,
            ]);
        }

        return $this->render('employe/formations/quiz.html.twig', [
            'module'          => $module,
            'chapitre'        => $chapitre,
            'quiz'            => $quiz,
            'questions'       => $questions,
            'tentative'       => $tentativeExistante,
            'limiteAtteinte'  => $limiteAtteinte,
            'numeroTentative' => $numeroActuel + 1,   // numéro de la tentative en cours
        ]);
    }

    // ══════════════════════════════════════════
    // LISTE SIMULATIONS
    // ══════════════════════════════════════════
    #[Route('/simulations', name: 'employe_simulations')]
    public function simulations(EntityManagerInterface $em): Response
    {
        $employe     = $this->getEmploye();
        $simulations = $em->getRepository(SimulationInteractive::class)->findBy(
            ['estPublie' => true],
            ['dateCreation' => 'DESC']
        );

        $resultats = $em->getRepository(ResultatSimulation::class)->findBy(['employe' => $employe]);
        $faites    = [];
        foreach ($resultats as $r) {
            $faites[$r->getSimulation()->getId()] = $r;
        }

        return $this->render('employe/simulations/liste.html.twig', [
            'simulations'       => $simulations,
            'simulationsFaites' => $faites,
        ]);
    }

    // ══════════════════════════════════════════
    // JOUER UNE SIMULATION
    // Réussie → formation terminée → +5 vigilance, points pédagogiques depuis l'entité module/simulation
    // ══════════════════════════════════════════
    #[Route('/simulations/{id}/jouer', name: 'employe_simulation_jouer')]
    public function jouerSimulation(SimulationInteractive $simulation, Request $request, EntityManagerInterface $em): Response
    {
        $employe = $this->getEmploye();

        if ($request->isMethod('POST')) {
            $data          = json_decode($request->request->get('resultat_json', '{}'), true);
            $score         = (int)($data['score'] ?? 0);
            $totalQ        = (int)($data['total'] ?? 1);
            $correctes     = (int)($data['correctes'] ?? 0);
            $reponses      = $data['reponses'] ?? [];
            $tempsSecondes = (int)($data['temps_secondes'] ?? 0);
            $aReussi       = $score >= 75;

            $resultat = new ResultatSimulation();
            $resultat->setScore($score)->setNombreReponsesCorrectes($correctes)
                ->setNombreTotalQuestions($totalQ)->setReponses($reponses)
                ->setAReussi($aReussi)
                ->setPointsGagnes($aReussi ? $simulation->getPointsReussite() : 0)
                ->setTempsPasseSecondes($tempsSecondes)
                ->setDateDebut(new \DateTime('-' . $tempsSecondes . ' seconds'))
                ->setDateTermine(new \DateTime())
                ->setEmploye($employe)->setSimulation($simulation);

            $em->persist($resultat);

            if ($simulation->getModule()) {
                // Correction bug critique : filtrer par progression ACTIVE (dateTermine null)
                // pour ne pas marquer comme terminée une ancienne session.
                $progression = $em->getRepository(ProgressionModule::class)->findOneBy([
                    'employe'     => $employe,
                    'module'      => $simulation->getModule(),
                    'dateTermine' => null,   // ← uniquement la session active
                ]);
                if ($progression) {
                    // Lier le résultat à la progression active
                    $resultat->setProgression($progression);   // ← LIEN SESSION

                    if ($aReussi) {
                        $progression->setStatut('TERMINE')
                            ->setPourcentageProgression(100)
                            ->setDateTermine(new \DateTime());

                        // Scoring formation terminée : +5 vigilance + module completion bonus + simulation success points
                        $this->scoring->formationTerminee($employe);
                        $employe->ajouterPoints($simulation->getPointsReussite());
                        $employe->ajouterPoints($simulation->getModule()?->getPointsBonus() ?? 0);
                    }
                }
            } elseif ($aReussi) {
                // Simulation standalone : points pédagogiques de réussite
                $employe->ajouterPoints($simulation->getPointsReussite());
            }

            $em->flush();
            return $this->redirectToRoute('employe_simulation_resultat', ['id' => $resultat->getId()]);
        }

        $items = $this->preparerItems($simulation->getTypeSimulation(), $simulation->getContenuSimulation(), $employe);

        return $this->render('employe/simulations/jouer.html.twig', [
            'simulation' => $simulation,
            'employe'    => $employe,
            'items'      => $items,
            'type'       => $simulation->getTypeSimulation(),
        ]);
    }

    // ══════════════════════════════════════════
    // RÉSULTAT SIMULATION
    // ══════════════════════════════════════════
    #[Route('/simulations/resultat/{id}', name: 'employe_simulation_resultat')]
    public function resultatSimulation(ResultatSimulation $resultat): Response
    {
        if ($resultat->getEmploye()->getId() !== $this->getEmploye()->getId()) {
            throw $this->createAccessDeniedException();
        }
        return $this->render('employe/simulations/resultat.html.twig', [
            'resultat'   => $resultat,
            'simulation' => $resultat->getSimulation(),
        ]);
    }

    // ══════════════════════════════════════════
    // ANALYSEUR (VirusTotal)
    // ══════════════════════════════════════════
    #[Route('/analyseur', name: 'employe_analyseur')]
    public function analyseur(): Response
    {
        return $this->render('employe/analyseur.html.twig');
    }

    #[Route('/analyseur/url', name: 'employe_analyseur_url', methods: ['POST'])]
    public function analyserUrl(Request $request, HttpClientInterface $http): JsonResponse
    {
        $url    = trim($request->request->get('url', ''));
        $apiKey = $_ENV['VIRUSTOTAL_API_KEY'] ?? '';

        if (!$url) return $this->json(['erreur' => 'URL manquante.'], 400);
        if (!filter_var($url, FILTER_VALIDATE_URL)) return $this->json(['erreur' => 'Format d\'URL invalide.'], 400);
        if (!$apiKey) return $this->json(['erreur' => 'Clé API VirusTotal non configurée.'], 500);

        try {
            $resp = $http->request('POST', 'https://www.virustotal.com/api/v3/urls', [
                'headers' => ['x-apikey' => $apiKey, 'Content-Type' => 'application/x-www-form-urlencoded'],
                'body'    => 'url=' . urlencode($url),
            ]);
            $id = $resp->toArray()['data']['id'] ?? null;
            if (!$id) return $this->json(['erreur' => 'Impossible de soumettre l\'URL.'], 500);
            sleep(15);
            $analyse = $http->request('GET', "https://www.virustotal.com/api/v3/analyses/{$id}", [
                'headers' => ['x-apikey' => $apiKey],
            ]);
            $stats = $analyse->toArray()['data']['attributes']['stats'] ?? [];
            return $this->json($this->interpreterStats($stats, $url));
        } catch (\Exception $e) {
            return $this->json(['erreur' => 'Erreur : ' . $e->getMessage()], 500);
        }
    }

    #[Route('/analyseur/fichier', name: 'employe_analyseur_fichier', methods: ['POST'])]
    public function analyserFichier(Request $request, HttpClientInterface $http): JsonResponse
    {
        $fichier = $request->files->get('fichier');
        $apiKey  = $_ENV['VIRUSTOTAL_API_KEY'] ?? '';

        if (!$fichier) return $this->json(['erreur' => 'Aucun fichier reçu.'], 400);
        if ($fichier->getSize() > 32 * 1024 * 1024) return $this->json(['erreur' => 'Fichier trop volumineux.'], 400);
        if (!$apiKey) return $this->json(['erreur' => 'Clé API VirusTotal non configurée.'], 500);

        try {
            $resp = $http->request('POST', 'https://www.virustotal.com/api/v3/files', [
                'headers' => ['x-apikey' => $apiKey],
                'body'    => ['file' => fopen($fichier->getPathname(), 'r')],
            ]);
            $id = $resp->toArray()['data']['id'] ?? null;
            if (!$id) return $this->json(['erreur' => 'Impossible d\'envoyer le fichier.'], 500);
            sleep(5);
            $analyse = $http->request('GET', "https://www.virustotal.com/api/v3/analyses/{$id}", [
                'headers' => ['x-apikey' => $apiKey],
            ]);
            $stats = $analyse->toArray()['data']['attributes']['stats'] ?? [];
            return $this->json($this->interpreterStats($stats, $fichier->getClientOriginalName()));
        } catch (\Exception $e) {
            return $this->json(['erreur' => 'Erreur : ' . $e->getMessage()], 500);
        }
    }

    // ══════════════════════════════════════════
    // PROFIL
    // ══════════════════════════════════════════
    #[Route('/profil', name: 'employe_profil')]
    public function profil(EntityManagerInterface $em): Response
    {
        $employe = $this->getEmploye();
        return $this->render('employe/profil.html.twig', [
            'employe'       => $employe,
            'progressions'  => $em->getRepository(ProgressionModule::class)->findBy(['employe' => $employe]),
            'tentatives'    => $em->getRepository(TentativeQuiz::class)->findBy(['employe' => $employe], ['dateTermine' => 'DESC']),
            'resultatsSimu' => $em->getRepository(ResultatSimulation::class)->findBy(['employe' => $employe], ['dateTermine' => 'DESC']),
        ]);
    }

    #[Route('/profil/modifier', name: 'employe_profil_modifier', methods: ['GET', 'POST'])]
    public function modifierProfil(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $employe = $this->getEmploye();

        if ($request->isMethod('POST')) {
            $prenom    = trim($request->request->get('prenom', ''));
            $nom       = trim($request->request->get('nom', ''));
            $telephone = trim($request->request->get('telephone', ''));
            $mdpActuel = $request->request->get('mot_de_passe_actuel', '');
            $mdpNouv   = $request->request->get('nouveau_mot_de_passe', '');
            $mdpConf   = $request->request->get('confirmation_mot_de_passe', '');

            if (!$prenom || !$nom) {
                $this->addFlash('error', 'Le prénom et le nom sont obligatoires.');
                return $this->redirectToRoute('employe_profil_modifier');
            }

            $employe->setPrenom($prenom)->setNom($nom)->setTelephone($telephone ?: null);

            if ($mdpNouv) {
                if (!$hasher->isPasswordValid($employe, $mdpActuel)) {
                    $this->addFlash('error', 'Mot de passe actuel incorrect.');
                    return $this->redirectToRoute('employe_profil_modifier');
                }
                if ($mdpNouv !== $mdpConf) {
                    $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
                    return $this->redirectToRoute('employe_profil_modifier');
                }
                if (strlen($mdpNouv) < 8) {
                    $this->addFlash('error', 'Minimum 8 caractères.');
                    return $this->redirectToRoute('employe_profil_modifier');
                }
                $employe->setMotDePasse($hasher->hashPassword($employe, $mdpNouv));
            }

            $em->flush();
            $this->addFlash('success', 'Profil mis à jour avec succès !');
            return $this->redirectToRoute('employe_profil');
        }

        return $this->render('employe/profil_modifier.html.twig', ['employe' => $employe]);
    }

    // ══════════════════════════════════════════
    // HELPERS PRIVÉS
    // ══════════════════════════════════════════

    private function tousQuizChapitresReussis(array $chapitres, array $tentativesChapitres): bool
    {
        foreach ($chapitres as $chapitre) {
            if ($chapitre->getQuiz()) {
                $tentative = $tentativesChapitres[$chapitre->getId()] ?? null;
                if (!$tentative || !$tentative->isAReussi()) return false;
            }
        }
        return true;
    }

    private function interpreterStats(array $stats, string $nom): array
    {
        $malveillant = (int)($stats['malicious'] ?? 0);
        $suspect     = (int)($stats['suspicious'] ?? 0);
        $propre      = (int)($stats['harmless'] ?? 0) + (int)($stats['undetected'] ?? 0);
        $total       = $malveillant + $suspect + $propre + (int)($stats['timeout'] ?? 0);

        if ($malveillant >= 3) {
            $niveau = 'danger'; $message = 'Détecté comme malveillant. Ne l\'ouvrez pas.';
        } elseif ($malveillant >= 1 || $suspect >= 2) {
            $niveau = 'warning'; $message = 'Suspect. Soyez très prudent.';
        } else {
            $niveau = 'safe'; $message = 'Aucun problème détecté. Semble sûr.';
        }

        return compact('niveau', 'message', 'malveillant', 'suspect', 'propre', 'total', 'nom');
    }

    private function preparerItems(string $type, array $contenu, Employe $employe): array
    {
        $cle  = match($type) {
            'GMAIL'    => 'emails',
            'SMS'      => 'sms',
            'WHATSAPP' => 'conversations',
            default    => 'emails',
        };
        $tous = $contenu[$cle] ?? [];
        if (empty($tous)) return [];

        $phishing = array_values(array_filter($tous, fn($e) => $e['is_phishing'] ?? false));
        $legit    = array_values(array_filter($tous, fn($e) => !($e['is_phishing'] ?? false)));
        shuffle($phishing); shuffle($legit);

        $nb      = $contenu['nb_a_tirer'] ?? 4;
        $nbPhish = max(1, intdiv($nb, 2));
        $nbLegit = max(1, $nb - $nbPhish);
        $selection = array_merge(
            array_slice($phishing, 0, min($nbPhish, count($phishing))),
            array_slice($legit,    0, min($nbLegit,  count($legit)))
        );
        shuffle($selection);

        $replacements = [
            '{{PRENOM}}'     => $employe->getPrenom(),
            '{{NOM}}'        => $employe->getNom(),
            '{{EMAIL}}'      => $employe->getEmail(),
            '{{TELEPHONE}}'  => $employe->getTelephone() ?? '',
            '{{ENTREPRISE}}' => $employe->getEntreprise()?->getNom() ?? 'votre entreprise',
        ];

        array_walk_recursive($selection, function (&$val) use ($replacements) {
            if (is_string($val)) {
                $val = str_replace(array_keys($replacements), array_values($replacements), $val);
            }
        });

        return $selection;
    }
}