<?php

namespace App\Controller\Admin;

use App\Entity\Quiz;
use App\Entity\QuestionQuiz;
use App\Entity\ModuleFormation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/quiz')]
class QuizController extends AbstractController
{
    // ================================================================
    // LISTE — tous les quiz (avec leur module)
    // ================================================================
    #[Route('', name: 'admin_quiz_liste')]
    public function liste(EntityManagerInterface $em): Response
    {
        $quiz = $em->getRepository(Quiz::class)->findAll();

        return $this->render('admin/quiz/liste.html.twig', [
            'quiz' => $quiz,
        ]);
    }

    // ================================================================
    // NOUVEAU QUIZ — rattaché à un module
    // ================================================================
    #[Route('/nouveau', name: 'admin_quiz_nouveau')]
    public function nouveau(Request $request, EntityManagerInterface $em): Response
    {
        // Récupérer les modules sans quiz
        $tousModules = $em->getRepository(ModuleFormation::class)->findAll();
        $modulesDisponibles = array_filter($tousModules, fn($m) => $m->getQuiz() === null);

        if ($request->isMethod('POST')) {
            $moduleId = $request->request->get('module_id');
            $module = $em->getRepository(ModuleFormation::class)->find($moduleId);

            if (!$module) {
                $this->addFlash('danger', 'Module introuvable.');
                return $this->redirectToRoute('admin_quiz_nouveau');
            }

            if ($module->getQuiz() !== null) {
                $this->addFlash('danger', 'Ce module a déjà un quiz.');
                return $this->redirectToRoute('admin_quiz_nouveau');
            }

            $quiz = new Quiz();
            $quiz->setTitre($request->request->get('titre'))
                ->setDescription($request->request->get('description'))
                ->setScoreMinimum((int) $request->request->get('score_minimum', 70))
                ->setNombreTentativesMax((int) $request->request->get('tentatives_max', 3))
                ->setMelangerQuestions($request->request->get('melanger_questions') === '1')
                ->setModule($module);

            $tempsLimite = $request->request->get('temps_limite');
            if ($tempsLimite !== '' && $tempsLimite !== null) {
                $quiz->setTempsLimite((int) $tempsLimite);
            }

            $em->persist($quiz);
            $em->flush();

            $this->addFlash('success', 'Quiz créé. Ajoutez maintenant les questions.');
            return $this->redirectToRoute('admin_quiz_questions', ['id' => $quiz->getId()]);
        }

        return $this->render('admin/quiz/nouveau.html.twig', [
            'modules' => $modulesDisponibles,
        ]);
    }

    // ================================================================
    // MODIFIER UN QUIZ (paramètres généraux seulement)
    // ================================================================
    #[Route('/{id}/modifier', name: 'admin_quiz_modifier')]
    public function modifier(Quiz $quiz, Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $quiz->setTitre($request->request->get('titre'))
                ->setDescription($request->request->get('description'))
                ->setScoreMinimum((int) $request->request->get('score_minimum', 70))
                ->setNombreTentativesMax((int) $request->request->get('tentatives_max', 3))
                ->setMelangerQuestions($request->request->get('melanger_questions') === '1');

            $tempsLimite = $request->request->get('temps_limite');
            $quiz->setTempsLimite(($tempsLimite !== '' && $tempsLimite !== null) ? (int) $tempsLimite : null);

            $em->flush();

            $this->addFlash('success', 'Quiz mis à jour.');
            return $this->redirectToRoute('admin_quiz_questions', ['id' => $quiz->getId()]);
        }

        return $this->render('admin/quiz/modifier.html.twig', [
            'quiz' => $quiz,
        ]);
    }

    // ================================================================
    // GESTION DES QUESTIONS (page principale du quiz)
    // ================================================================
    #[Route('/{id}/questions', name: 'admin_quiz_questions')]
    public function questions(Quiz $quiz): Response
    {
        return $this->render('admin/quiz/questions.html.twig', [
            'quiz' => $quiz,
        ]);
    }

    // ================================================================
    // AJOUTER UNE QUESTION
    // ================================================================
    #[Route('/{id}/questions/ajouter', name: 'admin_quiz_question_ajouter', methods: ['POST'])]
    public function ajouterQuestion(Quiz $quiz, Request $request, EntityManagerInterface $em): Response
    {
        $typeQuestion = $request->request->get('type_question');
        $optionsRaw   = $request->request->all('options') ?? [];
        $reponsesRaw  = $request->request->all('reponses_correctes') ?? [];

        // Nettoyer les options vides
        $options = array_values(array_filter($optionsRaw, fn($o) => trim($o) !== ''));

        // Pour VRAI_FAUX, construire les options automatiquement
        if ($typeQuestion === 'VRAI_FAUX') {
            $options = ['Vrai', 'Faux'];
        }

        $question = new QuestionQuiz();
        $question->setQuestion($request->request->get('question'))
            ->setTypeQuestion($typeQuestion)
            ->setOptions($options)
            ->setReponsesCorrectes($reponsesRaw)
            ->setPoints((int) $request->request->get('points', 10))
            ->setOrdre($quiz->getQuestions()->count() + 1)
            ->setExplication($request->request->get('explication') ?: null)
            ->setQuiz($quiz);

        $em->persist($question);
        $em->flush();

        $this->addFlash('success', 'Question ajoutée.');
        return $this->redirectToRoute('admin_quiz_questions', ['id' => $quiz->getId()]);
    }

    // ================================================================
    // SUPPRIMER UNE QUESTION
    // ================================================================
    #[Route('/{quizId}/questions/{id}/supprimer', name: 'admin_quiz_question_supprimer', methods: ['POST'])]
    public function supprimerQuestion(
        int $quizId,
        QuestionQuiz $question,
        EntityManagerInterface $em
    ): Response {
        $em->remove($question);
        $em->flush();

        // Réordonner les questions restantes
        $quiz = $em->getRepository(Quiz::class)->find($quizId);
        if ($quiz) {
            foreach ($quiz->getQuestions() as $i => $q) {
                $q->setOrdre($i + 1);
            }
            $em->flush();
        }

        $this->addFlash('success', 'Question supprimée.');
        return $this->redirectToRoute('admin_quiz_questions', ['id' => $quizId]);
    }

    // ================================================================
    // MONTER / DESCENDRE UNE QUESTION
    // ================================================================
    #[Route('/{quizId}/questions/{id}/monter', name: 'admin_quiz_question_monter', methods: ['POST'])]
    public function monterQuestion(int $quizId, QuestionQuiz $question, EntityManagerInterface $em): Response
    {
        $quiz = $em->getRepository(Quiz::class)->find($quizId);
        if (!$quiz) {
            return $this->redirectToRoute('admin_quiz_liste');
        }

        $questions = $quiz->getQuestions()->toArray();
        usort($questions, fn($a, $b) => $a->getOrdre() - $b->getOrdre());

        $idx = array_search($question, $questions);
        if ($idx > 0) {
            $prev = $questions[$idx - 1];
            $tmpOrdre = $prev->getOrdre();
            $prev->setOrdre($question->getOrdre());
            $question->setOrdre($tmpOrdre);
            $em->flush();
        }

        return $this->redirectToRoute('admin_quiz_questions', ['id' => $quizId]);
    }

    #[Route('/{quizId}/questions/{id}/descendre', name: 'admin_quiz_question_descendre', methods: ['POST'])]
    public function descendreQuestion(int $quizId, QuestionQuiz $question, EntityManagerInterface $em): Response
    {
        $quiz = $em->getRepository(Quiz::class)->find($quizId);
        if (!$quiz) {
            return $this->redirectToRoute('admin_quiz_liste');
        }

        $questions = $quiz->getQuestions()->toArray();
        usort($questions, fn($a, $b) => $a->getOrdre() - $b->getOrdre());

        $idx = array_search($question, $questions);
        if ($idx < count($questions) - 1) {
            $next = $questions[$idx + 1];
            $tmpOrdre = $next->getOrdre();
            $next->setOrdre($question->getOrdre());
            $question->setOrdre($tmpOrdre);
            $em->flush();
        }

        return $this->redirectToRoute('admin_quiz_questions', ['id' => $quizId]);
    }

    // ================================================================
    // SUPPRIMER UN QUIZ
    // ================================================================
    #[Route('/{id}/supprimer', name: 'admin_quiz_supprimer', methods: ['POST'])]
    public function supprimer(Quiz $quiz, EntityManagerInterface $em): Response
    {
        $em->remove($quiz);
        $em->flush();

        $this->addFlash('success', 'Quiz supprimé.');
        return $this->redirectToRoute('admin_quiz_liste');
    }
}