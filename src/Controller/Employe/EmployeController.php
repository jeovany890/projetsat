<?php

namespace App\Controller\Employe;

use App\Entity\Employe;
use App\Entity\ProgressionModule;
use App\Entity\ModuleFormation;
use App\Entity\Chapitre;
use App\Entity\TentativeQuiz;
use App\Entity\SimulationInteractive;
use App\Entity\ResultatSimulation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/employe')]
#[IsGranted('ROLE_EMPLOYE')]
class EmployeController extends AbstractController
{
    private function getEmploye(): Employe
    {
        /** @var Employe $employe */
        return $this->getUser();
    }

    // ════════════════════════════════════════════
    // DASHBOARD
    // ════════════════════════════════════════════
    #[Route('', name: 'employe_dashboard')]
    public function dashboard(EntityManagerInterface $em): Response
    {
        $employe = $this->getEmploye();

        // Progressions de l'employé
        $progressions = $em->getRepository(ProgressionModule::class)->findBy(
            ['employe' => $employe],
            ['dateDernierAcces' => 'DESC']
        );

        // Stats formations
        $total    = count($progressions);
        $termines = count(array_filter($progressions, fn($p) => $p->getStatut() === 'TERMINE'));
        $enCours  = count(array_filter($progressions, fn($p) => $p->getStatut() === 'EN_COURS'));
        $enRetard = count(array_filter($progressions, fn($p) => $p->isEstEnRetard()));

        // Afficher seulement les 3 premières en cours sur le dashboard
        $progressionsEnCours = array_filter($progressions, fn($p) => $p->getStatut() !== 'TERMINE');
        $progressionsEnCours = array_slice(array_values($progressionsEnCours), 0, 3);

        return $this->render('employe/dashboard.html.twig', [
            'progressions'    => $progressionsEnCours,
            'statsFormations' => [
                'total'    => $total,
                'terminees' => $termines,
                'enCours'  => $enCours,
                'enRetard' => $enRetard,
            ],
        ]);
    }

    // ════════════════════════════════════════════
    // LISTE DES FORMATIONS
    // ════════════════════════════════════════════
    #[Route('/formations', name: 'employe_formations')]
    public function formations(EntityManagerInterface $em): Response
    {
        $employe = $this->getEmploye();

        $progressions = $em->getRepository(ProgressionModule::class)->findBy(
            ['employe' => $employe],
            ['echeance' => 'ASC']
        );

        return $this->render('employe/formations/liste.html.twig', [
            'progressions' => $progressions,
        ]);
    }

    // ════════════════════════════════════════════
    // DÉTAIL D'UNE FORMATION (chapitres + quiz)
    // ════════════════════════════════════════════
    #[Route('/formations/{id}', name: 'employe_formation_detail', requirements: ['id' => '\d+'])]
    public function formationDetail(
        ModuleFormation $module,
        EntityManagerInterface $em
    ): Response {
        $employe = $this->getEmploye();

        // Trouver ou créer la progression
        $progression = $em->getRepository(ProgressionModule::class)->findOneBy([
            'employe' => $employe,
            'module'  => $module,
        ]);

        if (!$progression) {
            // Créer une progression si l'accès est libre
            $progression = new ProgressionModule();
            $progression->setEmploye($employe)
                ->setModule($module)
                ->setStatut('EN_COURS')
                ->setDateDebut(new \DateTime());
            $em->persist($progression);
            $em->flush();
        } elseif ($progression->getStatut() === 'NON_COMMENCE') {
            $progression->setStatut('EN_COURS')->setDateDebut(new \DateTime());
            $em->flush();
        }

        // Mettre à jour dernier accès
        $progression->setDateDernierAcces(new \DateTime());
        $em->flush();

        // Vérifier si quiz déjà passé
        $tentative = $module->getQuiz() ? $em->getRepository(TentativeQuiz::class)->findOneBy([
            'employe' => $employe,
            'quiz'    => $module->getQuiz(),
        ]) : null;

        return $this->render('employe/formations/detail.html.twig', [
            'module'      => $module,
            'progression' => $progression,
            'tentative'   => $tentative,
            'chapitres'   => $module->getChapitres(),
        ]);
    }

    // ════════════════════════════════════════════
    // MARQUER UN CHAPITRE COMME LU
    // ════════════════════════════════════════════
    #[Route('/formations/{moduleId}/chapitre/{chapitreId}/terminer', name: 'employe_chapitre_terminer', methods: ['POST'])]
    public function terminerChapitre(
        int $moduleId,
        int $chapitreId,
        EntityManagerInterface $em
    ): Response {
        $employe = $this->getEmploye();
        $module  = $em->getRepository(ModuleFormation::class)->find($moduleId);
        if (!$module) throw $this->createNotFoundException();

        $progression = $em->getRepository(ProgressionModule::class)->findOneBy([
            'employe' => $employe,
            'module'  => $module,
        ]);

        if ($progression) {
            $totalChapitres = count($module->getChapitres());
            // Calculer progression (simulé par ordre du chapitre)
            $chapitre = $em->getRepository(Chapitre::class)->find($chapitreId);
            if ($chapitre && $totalChapitres > 0) {
                // Trouver la position du chapitre
                $chapitres = $module->getChapitres()->toArray();
                $pos = array_search($chapitre, $chapitres);
                $nouveauPourcentage = (int)(($pos + 1) / $totalChapitres * (($module->getQuiz()) ? 80 : 100));
                if ($nouveauPourcentage > $progression->getPourcentageProgression()) {
                    $progression->setPourcentageProgression($nouveauPourcentage);
                }
            }
            $progression->setDateDernierAcces(new \DateTime());
            $em->flush();
        }

        return $this->redirectToRoute('employe_formation_detail', ['id' => $moduleId]);
    }

    // ════════════════════════════════════════════
    // QUIZ
    // ════════════════════════════════════════════
    #[Route('/formations/{id}/quiz', name: 'employe_quiz', requirements: ['id' => '\d+'])]
    public function quiz(
        ModuleFormation $module,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $employe = $this->getEmploye();
        $quiz    = $module->getQuiz();

        if (!$quiz) {
            $this->addFlash('error', 'Ce module n\'a pas de quiz.');
            return $this->redirectToRoute('employe_formation_detail', ['id' => $module->getId()]);
        }

        // Vérifier si déjà passé
        $tentativeExistante = $em->getRepository(TentativeQuiz::class)->findOneBy([
            'employe' => $employe,
            'quiz'    => $quiz,
        ]);

        if ($request->isMethod('POST')) {
            $reponses   = $request->request->all('reponses');
            $questions  = $quiz->getQuestions()->toArray();
            $score      = 0;
            $total      = count($questions);
            $resultats  = [];

            foreach ($questions as $question) {
                $reponseDonnee = $reponses[$question->getId()] ?? null;
                $correct = ($reponseDonnee == $question->getReponsesCorrectes()[0] ?? null);
                if ($correct) $score++;
                $resultats[$question->getId()] = [
                    'correct'       => $correct,
                    'donnee'        => $reponseDonnee,
                    'bonne'         => $question->getReponsesCorrectes()[0] ?? null,
                    'explication'   => $question->getExplication(),
                ];
            }

            $pourcentage = $total > 0 ? (int)(($score / $total) * 100) : 0;
            $reussi = $pourcentage >= ($quiz->getScoreMinimum());

            // Enregistrer la tentative
            $tentative = $tentativeExistante ?? new TentativeQuiz();
            $tentative->setEmploye($employe)
                ->setQuiz($quiz)
                ->setScore($score)
                ->setTotalQuestions($total)
                ->setReponsesCorrectes($score)
                ->setAReussi($reussi)
                ->setNumeroTentative(1)
                ->setDateDebut(new \DateTime())
                ->setDateTermine(new \DateTime());
            $em->persist($tentative);

            // Mettre à jour la progression
            $progression = $em->getRepository(ProgressionModule::class)->findOneBy([
                'employe' => $employe,
                'module'  => $module,
            ]);
            if ($progression) {
                $progression->setScoreQuiz($pourcentage);
                if ($reussi) {
                    $progression->setStatut('TERMINE')
                        ->setPourcentageProgression(100)
                        ->setDateTermine(new \DateTime());
                    // Récompenses
                    $employe->ajouterPoints($reussi ? 100 : 30);
                    $employe->ajouterEtoiles($reussi ? 1 : 0);
                }
            }
            $em->flush();

            return $this->render('employe/formations/quiz_resultat.html.twig', [
                'module'     => $module,
                'quiz'       => $quiz,
                'score'      => $score,
                'total'      => $total,
                'pourcentage' => $pourcentage,
                'reussi'     => $reussi,
                'resultats'  => $resultats,
                'questions'  => $questions,
            ]);
        }

        return $this->render('employe/formations/quiz.html.twig', [
            'module'    => $module,
            'quiz'      => $quiz,
            'questions' => $quiz->getQuestions()->toArray(),
            'tentative' => $tentativeExistante,
        ]);
    }

    // ════════════════════════════════════════════
    // SIMULATIONS
    // ════════════════════════════════════════════
    #[Route('/simulations', name: 'employe_simulations')]
    public function simulations(EntityManagerInterface $em): Response
    {
        $simulations = $em->getRepository(SimulationInteractive::class)->findBy(
            ['estActif' => true],
            ['dateCreation' => 'DESC']
        );

        $employe = $this->getEmploye();
        $resultats = $em->getRepository(ResultatSimulation::class)->findBy(
            ['employe' => $employe]
        );

        $simulationsFaites = [];
        foreach ($resultats as $r) {
            $simulationsFaites[$r->getSimulation()->getId()] = $r;
        }

        return $this->render('employe/simulations/liste.html.twig', [
            'simulations'       => $simulations,
            'simulationsFaites' => $simulationsFaites,
        ]);
    }

    // ════════════════════════════════════════════
    // PROFIL
    // ════════════════════════════════════════════
    #[Route('/profil', name: 'employe_profil')]
    public function profil(EntityManagerInterface $em): Response
    {
        $employe = $this->getEmploye();

        $progressions = $em->getRepository(ProgressionModule::class)->findBy(['employe' => $employe]);
        $tentatives   = $em->getRepository(TentativeQuiz::class)->findBy(
            ['employe' => $employe],
            ['dateTermine' => 'DESC']
        );

        return $this->render('employe/profil.html.twig', [
            'employe'     => $employe,
            'progressions' => $progressions,
            'tentatives'  => $tentatives,
        ]);
    }
}