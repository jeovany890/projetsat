<?php

namespace App\Controller\Admin;

use App\Entity\Quiz;
use App\Entity\ModuleFormation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/quiz')]
#[IsGranted('ROLE_ADMIN')]
class QuizController extends AbstractController
{
    // =========================================================
    // LISTE — cherche le quiz initial par typeQuiz=INITIAL
    // CORRECTION : était module=null && chapitre=null (cassé)
    // =========================================================
    #[Route('', name: 'admin_quiz_liste')]
    public function liste(EntityManagerInterface $em): Response
    {
        $quiz        = $em->getRepository(Quiz::class)->findAll();
        $quizInitial = null;

        foreach ($quiz as $q) {
            if ($q->getTypeQuiz() === Quiz::TYPE_INITIAL) { // ← CORRIGÉ
                $quizInitial = $q;
                break;
            }
        }

        return $this->render('admin/quiz/liste.html.twig', [
            'quiz'        => $quiz,
            'quizInitial' => $quizInitial,
        ]);
    }

    // =========================================================
    // QUIZ INITIAL — création et affichage
    // CORRECTION 1 : findOneBy par typeQuiz=INITIAL
    // CORRECTION 2 : setTypeQuiz(TYPE_INITIAL) à la création
    // =========================================================
    

    // =========================================================
    // NOUVEAU QUIZ (pour les chapitres — TYPE_CHAPITRE)
    // Pas de changement ici, déjà correct
    // =========================================================
    #[Route('/nouveau', name: 'admin_quiz_nouveau')]
    public function nouveau(Request $request, EntityManagerInterface $em): Response
    {
        $modules = $em->getRepository(ModuleFormation::class)->findAll();

        if ($request->isMethod('POST')) {
            $moduleId = $request->request->get('module_id');
            $module   = $em->getRepository(ModuleFormation::class)->find($moduleId);

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
                ->setModule($module);
            $em->persist($quiz);
            $em->flush();

            $this->addFlash('success', 'Quiz créé. Ajoutez maintenant les questions.');
            return $this->redirectToRoute('admin_quiz_questions', ['id' => $quiz->getId()]);
        }

        return $this->render('admin/quiz/nouveau.html.twig', [
            'modules' => $modules,
        ]);
    }

    // =========================================================
    // MODIFIER UN QUIZ
    // =========================================================
    #[Route('/{id}/modifier', name: 'admin_quiz_modifier')]
    public function modifier(Quiz $quiz, Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $quiz->setTitre($request->request->get('titre'))
                ->setDescription($request->request->get('description'))
                ->setScoreMinimum((int) $request->request->get('score_minimum', 70))
                ->setNombreTentativesMax((int) $request->request->get('tentatives_max', 3));
            $em->flush();

            $this->addFlash('success', 'Quiz mis à jour.');
            return $this->redirectToRoute('admin_quiz_questions', ['id' => $quiz->getId()]);
        }

        return $this->render('admin/quiz/modifier.html.twig', ['quiz' => $quiz]);
    }

    // =========================================================
    // LISTE DES QUESTIONS D'UN QUIZ
    // =========================================================
    #[Route('/{id}/questions', name: 'admin_quiz_questions')]
    public function questions(Quiz $quiz): Response
    {
        return $this->render('admin/quiz/questions.html.twig', ['quiz' => $quiz]);
    }

    // =========================================================
    // AJOUTER UNE QUESTION (stockée en JSON)
    // NOUVEAU : ajout du champ `categorie` pour le quiz initial
    // =========================================================
    #[Route('/{id}/questions/ajouter', name: 'admin_quiz_question_ajouter', methods: ['POST'])]
    public function ajouterQuestion(Quiz $quiz, Request $request, EntityManagerInterface $em): Response
    {
        $typeQuestion = $request->request->get('type_question');
        $optionsRaw   = $request->request->all('options') ?? [];
        $reponsesRaw  = $request->request->all('reponses_correctes') ?? [];

        $options = array_values(array_filter($optionsRaw, fn($o) => trim($o) !== ''));
        if ($typeQuestion === 'VRAI_FAUX') {
            $options = ['Vrai', 'Faux'];
        }

        $newQuestion = [
            'question'            => $request->request->get('question'),
            'type_question'       => $typeQuestion,
            'options'             => $options,
            'reponses_correctes'  => $reponsesRaw,
            'points'              => (int) $request->request->get('points', 10),
            'explication'         => $request->request->get('explication') ?: null,
        ];

        // NOUVEAU : champ categorie uniquement pour le quiz INITIAL
        // Valeurs autorisées : phishing | smishing | bonnes_pratiques
        if ($quiz->estInitial()) {
            $categorie             = $request->request->get('categorie', 'bonnes_pratiques');
            $categoriesAutorisees  = ['phishing', 'smishing', 'bonnes_pratiques'];
            $newQuestion['categorie'] = in_array($categorie, $categoriesAutorisees)
                ? $categorie
                : 'bonnes_pratiques';
        }

        $questions   = $quiz->getQuestions() ?? [];
        $questions[] = $newQuestion;
        $quiz->setQuestions($questions);
        $em->flush();

        $this->addFlash('success', 'Question ajoutée.');
        return $this->redirectToRoute('admin_quiz_questions', ['id' => $quiz->getId()]);
    }

    // =========================================================
    // SUPPRIMER UNE QUESTION PAR SON INDEX JSON
    // =========================================================
    #[Route('/{quizId}/questions/{index}/supprimer', name: 'admin_quiz_question_supprimer', methods: ['POST'])]
    public function supprimerQuestion(int $quizId, int $index, EntityManagerInterface $em): Response
    {
        $quiz = $em->getRepository(Quiz::class)->find($quizId);

        if (!$quiz) {
            throw $this->createNotFoundException('Quiz non trouvé');
        }

        $questions = $quiz->getQuestions() ?? [];

        if (isset($questions[$index])) {
            array_splice($questions, $index, 1);
            $quiz->setQuestions($questions);
            $em->flush();
            $this->addFlash('success', 'Question supprimée.');
        } else {
            $this->addFlash('error', 'Question introuvable.');
        }

        return $this->redirectToRoute('admin_quiz_questions', ['id' => $quizId]);
    }

    // =========================================================
    // MODIFIER UNE QUESTION
    // =========================================================
    #[Route('/{id}/questions/{index}/modifier', name: 'admin_quiz_question_modifier')]
    public function modifierQuestion(Quiz $quiz, int $index, Request $request, EntityManagerInterface $em): Response
    {
        $questions = $quiz->getQuestions() ?? [];

        if (!isset($questions[$index])) {
            throw $this->createNotFoundException('Question introuvable');
        }

        $question = $questions[$index];

        if ($request->isMethod('POST')) {
            $typeQuestion = $request->request->get('type_question');
            $optionsRaw   = $request->request->all('options') ?? [];
            $reponsesRaw  = $request->request->all('reponses_correctes') ?? [];

            $options = array_values(array_filter($optionsRaw, fn($o) => trim($o) !== ''));
            if ($typeQuestion === 'VRAI_FAUX') {
                $options = ['Vrai', 'Faux'];
            }

            $newQuestion = [
                'question'           => $request->request->get('question'),
                'type_question'      => $typeQuestion,
                'options'            => $options,
                'reponses_correctes' => $reponsesRaw,
                'points'             => (int) $request->request->get('points', 10),
                'explication'        => $request->request->get('explication') ?: null,
            ];

            if ($quiz->estInitial()) {
                $categorie            = $request->request->get('categorie', 'bonnes_pratiques');
                $categoriesAutorisees = ['phishing', 'smishing', 'bonnes_pratiques'];
                $newQuestion['categorie'] = in_array($categorie, $categoriesAutorisees)
                    ? $categorie
                    : 'bonnes_pratiques';
            }

            $questions[$index] = $newQuestion;
            $quiz->setQuestions($questions);
            $em->flush();

            $this->addFlash('success', 'Question mise à jour.');
            return $this->redirectToRoute('admin_quiz_questions', ['id' => $quiz->getId()]);
        }

        return $this->render('admin/quiz/question_modifier.html.twig', [
            'quiz' => $quiz,
            'index' => $index,
            'question' => $question,
        ]);
    }

    // =========================================================
    // MODIFIER TOUTES LES QUESTIONS D'UN QUIZ
    // =========================================================
    #[Route('/{id}/questions/modifier-toutes', name: 'admin_quiz_questions_modifier_toutes')]
    public function modifierToutesQuestions(Quiz $quiz, Request $request, EntityManagerInterface $em): Response
    {
        $questions = $quiz->getQuestions() ?? [];

        if ($request->isMethod('POST')) {
            $updatedQuestions = [];
            $questionsData = $request->request->all('questions');

            foreach ($questionsData as $index => $qData) {
                $typeQuestion = $qData['type_question'] ?? 'QCM_UNIQUE';
                $optionsRaw = $qData['options'] ?? [];
                $reponsesRaw = $qData['reponses_correctes'] ?? [];

                $options = array_values(array_filter($optionsRaw, fn($o) => trim($o) !== ''));
                if ($typeQuestion === 'VRAI_FAUX') {
                    $options = ['Vrai', 'Faux'];
                }

                $updatedQuestion = [
                    'question' => $qData['question'] ?? '',
                    'type_question' => $typeQuestion,
                    'options' => $options,
                    'reponses_correctes' => $reponsesRaw,
                    'points' => (int)($qData['points'] ?? 10),
                    'explication' => $qData['explication'] ?: null,
                ];

                if ($quiz->estInitial() && isset($qData['categorie'])) {
                    $categorie = $qData['categorie'];
                    $categoriesAutorisees = ['phishing', 'smishing', 'bonnes_pratiques'];
                    $updatedQuestion['categorie'] = in_array($categorie, $categoriesAutorisees)
                        ? $categorie
                        : 'bonnes_pratiques';
                }

                $updatedQuestions[] = $updatedQuestion;
            }

            $quiz->setQuestions($updatedQuestions);
            $em->flush();

            $this->addFlash('success', 'Toutes les questions ont été mises à jour.');
            return $this->redirectToRoute('admin_quiz_questions', ['id' => $quiz->getId()]);
        }

        return $this->render('admin/quiz/questions_modifier_toutes.html.twig', [
            'quiz' => $quiz,
            'questions' => $questions,
        ]);
    }

    // =========================================================
    // SUPPRIMER UN QUIZ
    // =========================================================
    #[Route('/{id}/supprimer', name: 'admin_quiz_supprimer', methods: ['POST'])]
    public function supprimer(int $id, EntityManagerInterface $em): Response
    {
        try {
            $conn = $em->getConnection();
            $conn->executeStatement('DELETE FROM quiz WHERE id = :id', ['id' => $id]);
            $this->addFlash('success', 'Quiz supprimé.');
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erreur lors de la suppression : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_quiz_liste');
    }
}