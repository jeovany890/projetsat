<?php

namespace App\Controller\Employe;

use App\Entity\Employe;
use App\Entity\ProgressionModule;
use App\Entity\ModuleFormation;
use App\Entity\Chapitre;
use App\Entity\TentativeQuiz;
use App\Entity\ResultatSimulation;
use App\Service\ScoringMoteurService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
    // ══════════════════════════════════════════
    #[Route('/formations/{id}/commencer', name: 'employe_formation_commencer', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function commencerFormation(ModuleFormation $module, EntityManagerInterface $em): Response
    {
        $employe = $this->getEmploye();

        $progressionPhishingActive = $em->getRepository(ProgressionModule::class)->findOneBy([
            'employe'         => $employe,
            'module'          => $module,
            'typeAttribution' => 'PHISHING',
            'dateTermine'     => null,
        ]);

        if ($progressionPhishingActive) {
            $this->addFlash('warning', 'Cette formation a déjà été attribuée suite à une campagne phishing. Veuillez la reprendre.');
            return $this->redirectToRoute('employe_formation_detail', ['id' => $module->getId()]);
        }

        $progressionActive = $em->getRepository(ProgressionModule::class)->findOneBy([
            'employe'     => $employe,
            'module'      => $module,
            'dateTermine' => null,
        ]);

        if ($progressionActive) {
            if ($progressionActive->getStatut() === 'NON_COMMENCE') {
                $progressionActive->setStatut('EN_COURS')->setDateDebut(new \DateTime());
            }
            $progressionActive->setDateDernierAcces(new \DateTime());
            $em->flush();
            return $this->redirectToRoute('employe_formation_detail', ['id' => $module->getId()]);
        }

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

        return $this->redirectToRoute('employe_formation_detail', ['id' => $module->getId()]);
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

        if (!$progression) {
            $progressions = $em->getRepository(ProgressionModule::class)->findBy(
                ['employe' => $employe, 'module' => $module],
                ['id' => 'DESC']
            );
            foreach ($progressions as $p) {
                if ($p->getStatut() !== 'TERMINE') { $progression = $p; break; }
            }
            if (!$progression && !empty($progressions)) {
                $progression = $progressions[0];
            }
        }

        if (!$progression) {
            $this->addFlash('info', 'Veuillez cliquer sur "Commencer" pour démarrer cette formation.');
            return $this->redirectToRoute('employe_formations');
        }

        if ($progression->getStatut() === 'NON_COMMENCE') {
            $progression->setStatut('EN_COURS')->setDateDebut(new \DateTime());
        }
        $progression->setDateDernierAcces(new \DateTime());
        $em->flush();

        $chapitres = $module->getChapitres()->toArray();
        usort($chapitres, fn($a, $b) => $a->getId() <=> $b->getId());

        $tentativesChapitres = [];
        foreach ($chapitres as $chapitre) {
            if ($chapitre->hasQuiz()) {
                $tentative = $em->getRepository(TentativeQuiz::class)->findOneBy([
                    'employe'     => $employe,
                    'chapitre'    => $chapitre,
                    'progression' => $progression,
                ]);
                $tentativesChapitres[$chapitre->getId()] = $tentative;
            }
        }

        $tousChapitresTermines = $this->tousQuizChapitresReussis($chapitres, $tentativesChapitres);

        $resultatSimulation = null;
        if ($module->hasSimulation()) {
            $resultatSimulation = $em->getRepository(ResultatSimulation::class)->findOneBy([
                'employe'     => $employe,
                'module'      => $module,
                'progression' => $progression,
            ]);
        }

        $chapitreActifObj = null;
        if ($chapitreActifId > 0) {
            foreach ($chapitres as $chap) {
                if ($chap->getId() === $chapitreActifId) { $chapitreActifObj = $chap; break; }
            }
        }
        if (!$chapitreActifObj) {
            foreach ($chapitres as $chap) {
                $t    = $tentativesChapitres[$chap->getId()] ?? null;
                $done = !$chap->hasQuiz() || ($t && $t->isAReussi());
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

        if (!$module || !$chapitre) throw $this->createNotFoundException();

        $progression = $em->getRepository(ProgressionModule::class)->findOneBy([
            'employe'     => $employe,
            'module'      => $module,
            'dateTermine' => null,
        ]);

        if ($progression) {
            $totalChapitres = count($module->getChapitres());
            if ($totalChapitres > 0) {
                $chapitres = $module->getChapitres()->toArray();
                usort($chapitres, fn($a, $b) => $a->getId() <=> $b->getId());
                $pos     = array_search($chapitre, $chapitres);
                $max     = $module->hasSimulation() ? 70 : 90;
                $nouveau = (int)(($pos + 1) / $totalChapitres * $max);
                if ($nouveau > $progression->getPourcentageProgression()) {
                    $progression->setPourcentageProgression($nouveau);
                }
            }
            $progression->setDateDernierAcces(new \DateTime());
            $em->flush();
        }

        if ($chapitre->hasQuiz()) {
            return $this->redirectToRoute('employe_chapitre_quiz', [
                'moduleId'   => $moduleId,
                'chapitreId' => $chapitreId,
            ]);
        }

        return $this->redirectToRoute('employe_formation_detail', ['id' => $moduleId]);
    }

    // ══════════════════════════════════════════
    // QUIZ D'UN CHAPITRE
    // ══════════════════════════════════════════
    #[Route('/formations/{moduleId}/chapitre/{chapitreId}/quiz', name: 'employe_chapitre_quiz')]
    public function quizChapitre(int $moduleId, int $chapitreId, Request $request, EntityManagerInterface $em): Response
    {
        $employe  = $this->getEmploye();
        $module   = $em->getRepository(ModuleFormation::class)->find($moduleId);
        $chapitre = $em->getRepository(Chapitre::class)->find($chapitreId);

        if (!$module || !$chapitre || !$chapitre->hasQuiz()) throw $this->createNotFoundException();

        $questions            = $chapitre->getQuizQuestions() ?? [];
        $scoreMinimum         = $chapitre->getQuizScoreMinimum() ?? 70;
        $nombreTentativesMax  = $chapitre->getQuizNombreTentativesMax() ?? 3;

        $progressionActive = $em->getRepository(ProgressionModule::class)->findOneBy([
            'employe'     => $employe,
            'module'      => $module,
            'dateTermine' => null,
        ]);

        $tentativeExistante = $progressionActive
            ? $em->getRepository(TentativeQuiz::class)->findOneBy([
                'employe'     => $employe,
                'chapitre'    => $chapitre,
                'progression' => $progressionActive,
            ])
            : null;

        $numeroActuel   = $tentativeExistante?->getNumeroTentative() ?? 0;
        $dejaReussi     = $tentativeExistante?->isAReussi() ?? false;
        $limiteAtteinte = $dejaReussi || ($numeroActuel >= $nombreTentativesMax);

        if ($limiteAtteinte && $request->isMethod('POST')) {
            $this->addFlash('warning', $dejaReussi
                ? 'Vous avez déjà réussi ce quiz.'
                : sprintf('Nombre maximum de tentatives atteint (%d/%d).', $numeroActuel, $nombreTentativesMax));
            return $this->redirectToRoute('employe_formation_detail', ['id' => $moduleId]);
        }

        if ($request->isMethod('POST')) {
            $reponses  = $request->request->all('reponses');
            $score     = 0;
            $total     = count($questions);
            $resultats = [];

            foreach ($questions as $index => $q) {
                $rep     = $reponses[$index] ?? null;
                $bonnes  = $q['reponses_correctes'] ?? [];
                $correct = $rep !== null && in_array($rep, $bonnes);
                if ($correct) $score++;
                $resultats[$index] = [
                    'correct'     => $correct,
                    'donnee'      => $rep,
                    'bonne'       => $bonnes[0] ?? null,
                    'explication' => $q['explication'] ?? null,
                ];
            }

            $pourcentage = $total > 0 ? (int)(($score / $total) * 100) : 0;
            $reussi      = $pourcentage >= $scoreMinimum;

            $tentative = $tentativeExistante ?? new TentativeQuiz();
            $tentative->setEmploye($employe)
                ->setChapitre($chapitre)
                ->setReponsesCorrectes($score)
                ->setNumeroTentative($numeroActuel + 1)
                ->setReponses($resultats)
                ->setTempsPasseSecondes(0)
                ->setDateDebut(new \DateTime())
                ->setDateTermine(new \DateTime())
                ->setProgression($progressionActive);
            $em->persist($tentative);

            // Points du quiz
            $totalPoints = 0;
            foreach ($questions as $q) {
                $totalPoints += (int)($q['points'] ?? 0);
            }

            if ($reussi) {
                $this->scoring->quizReussi($employe);
                $employe->ajouterPoints($totalPoints);
            } else {
                $this->scoring->quizEchoue($employe);
            }

            $chapitresList = $module->getChapitres()->toArray();
            usort($chapitresList, fn($a, $b) => $a->getId() <=> $b->getId());
            $nbChap = count($chapitresList);

            $currentIdx = null;
            foreach ($chapitresList as $idx => $chap) {
                if ($chap->getId() === $chapitre->getId()) { $currentIdx = $idx; break; }
            }
            $chapitresSuivant   = ($currentIdx !== null && isset($chapitresList[$currentIdx + 1])) ? $chapitresList[$currentIdx + 1] : null;
            $estDernierChapitre = ($currentIdx !== null && $currentIdx === $nbChap - 1);

            $chapitresValides = 0;
            foreach ($chapitresList as $chap) {
                if (!$chap->hasQuiz()) { $chapitresValides++; continue; }
                if ($chap->getId() === $chapitre->getId()) {
                    if ($reussi) $chapitresValides++;
                } else {
                    $t = $em->getRepository(TentativeQuiz::class)->findOneBy([
                        'employe'     => $employe,
                        'chapitre'    => $chap,
                        'progression' => $progressionActive,
                    ]);
                    if ($t && $t->isAReussi()) $chapitresValides++;
                }
            }

            $aSimulation = $module->hasSimulation();
            $maxPct      = $aSimulation ? 90 : 100;
            $nouveauPct  = $nbChap > 0 ? (int)(($chapitresValides / $nbChap) * $maxPct) : 0;

            if ($progressionActive) {
                if ($nouveauPct > $progressionActive->getPourcentageProgression()) {
                    $progressionActive->setPourcentageProgression($nouveauPct);
                }
                $progressionActive->setDateDernierAcces(new \DateTime());
                if ($progressionActive->getStatut() === 'NON_COMMENCE') {
                    $progressionActive->setStatut('EN_COURS')->setDateDebut(new \DateTime());
                }
                if ($chapitresValides === $nbChap && !$aSimulation) {
                    $progressionActive->setStatut('TERMINE')
                        ->setPourcentageProgression(100)
                        ->setDateTermine(new \DateTime());
                    $this->scoring->formationTerminee($employe);
                    $employe->ajouterPoints($module->getPointsReussite());
                }
            }

            $em->flush();

            $tousTermines = ($chapitresValides === $nbChap);
            $simulation   = ($tousTermines && $estDernierChapitre && $aSimulation) ? $module : null;

            return $this->render('employe/formations/quiz_resultat.html.twig', [
                'module'             => $module,
                'chapitre'           => $chapitre,
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
            'questions'       => $questions,
            'tentative'       => $tentativeExistante,
            'limiteAtteinte'  => $limiteAtteinte,
            'numeroTentative' => $numeroActuel + 1,
        ]);
    }

    // ══════════════════════════════════════════
    // JOUER LA SIMULATION (intégrée au module)
    // ══════════════════════════════════════════
    #[Route('/formations/{id}/simulation', name: 'employe_simulation_jouer')]
    public function jouerSimulation(ModuleFormation $module, Request $request, EntityManagerInterface $em): Response
    {
        $employe = $this->getEmploye();

        if (!$module->hasSimulation()) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            $data          = json_decode($request->request->get('resultat_json', '{}'), true);
            $correctes     = (int)($data['correctes'] ?? 0);
            $reponses      = $data['reponses'] ?? [];
            $tempsSecondes = (int)($data['temps_secondes'] ?? 0);
            $totalQ        = count($module->getSimulationContenu()[$module->getSimulationType() === 'GMAIL' ? 'emails' : ($module->getSimulationType() === 'SMS' ? 'sms' : 'conversations')] ?? []);
            $aReussi       = $totalQ > 0 && ($correctes / $totalQ * 100) >= 75;

            $resultat = new ResultatSimulation();
            $resultat->setReponsesCorrectes($correctes)
                ->setReponses($reponses)
                ->setTempsPasseSecondes($tempsSecondes)
                ->setDateDebut(new \DateTime('-' . $tempsSecondes . ' seconds'))
                ->setDateTermine(new \DateTime())
                ->setEmploye($employe)
                ->setModule($module);

            $em->persist($resultat);

            // Trouver la progression active
            $progression = $em->getRepository(ProgressionModule::class)->findOneBy([
                'employe'     => $employe,
                'module'      => $module,
                'dateTermine' => null,
            ]);

            if ($progression) {
                $resultat->setProgression($progression);
                if ($aReussi) {
                    $progression->setStatut('TERMINE')
                        ->setPourcentageProgression(100)
                        ->setDateTermine(new \DateTime());
                    $this->scoring->formationTerminee($employe);
                    $employe->ajouterPoints($module->getPointsSimulation());
                }
            }

            $em->flush();
            return $this->redirectToRoute('employe_simulation_resultat', ['id' => $resultat->getId()]);
        }

        $items = $this->preparerItems($module->getSimulationType(), $module->getSimulationContenu(), $employe);

        return $this->render('employe/simulations/jouer.html.twig', [
            'module' => $module,
            'employe' => $employe,
            'items'   => $items,
            'type'    => $module->getSimulationType(),
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
            'resultat' => $resultat,
            'module'   => $resultat->getModule(),
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
        if (!$this->isAllowedUploadedFile($fichier)) return $this->json(['erreur' => 'Type de fichier non autorisé.'], 400);
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

    private function isAllowedUploadedFile(UploadedFile $file): bool
    {
        $allowedMimeTypes = [
            'application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain', 'application/rtf',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'image/png', 'image/jpeg', 'image/gif', 'application/zip',
        ];
        return in_array($file->getClientMimeType(), $allowedMimeTypes, true)
            && strtolower($file->getClientOriginalExtension()) !== '';
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
            if ($chapitre->hasQuiz()) {
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