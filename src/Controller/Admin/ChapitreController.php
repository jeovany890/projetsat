<?php

namespace App\Controller\Admin;

use App\Entity\Chapitre;
use App\Entity\ModuleFormation;
use App\Entity\Quiz;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/modules/{moduleId}/chapitres')]
#[IsGranted('ROLE_ADMIN')]
class ChapitreController extends AbstractController
{
    private function getModule(int $moduleId, EntityManagerInterface $em): ModuleFormation
    {
        $module = $em->getRepository(ModuleFormation::class)->find($moduleId);
        if (!$module) throw $this->createNotFoundException('Module non trouvé');
        return $module;
    }

    #[Route('', name: 'admin_chapitres_liste')]
    public function liste(int $moduleId, EntityManagerInterface $em): Response
    {
        $module    = $this->getModule($moduleId, $em);
        $chapitres = $em->getRepository(Chapitre::class)->findBy(['module' => $module], ['id' => 'ASC']);
        return $this->render('admin/chapitres/liste.html.twig', [
            'module'    => $module,
            'chapitres' => $chapitres,
        ]);
    }

    #[Route('/nouveau', name: 'admin_chapitre_nouveau')]
    public function nouveau(int $moduleId, Request $request, EntityManagerInterface $em): Response
    {
        $module = $this->getModule($moduleId, $em);
        if ($request->isMethod('POST')) {
            $titre   = $request->request->get('titre');
            $contenu = $request->request->get('contenu');
            if (empty($titre) || empty($contenu)) {
                $this->addFlash('error', 'Le titre et le contenu sont obligatoires.');
            } else {
                $chapitre = new Chapitre();
                $chapitre->setTitre($titre)->setContenu($contenu)
                    ->setUrlVideo($request->request->get('url_video') ?: null)
                    ->setDureeVideo($request->request->get('duree_video') ? (int)$request->request->get('duree_video') : null)
                    ->setModule($module);
                $em->persist($chapitre);
                $em->flush();
                $this->addFlash('success', ' Chapitre créé !');
                return $this->redirectToRoute('admin_chapitres_liste', ['moduleId' => $moduleId]);
            }
        }
        return $this->render('admin/chapitres/nouveau.html.twig', ['module' => $module]);
    }

    #[Route('/{id}/modifier', name: 'admin_chapitre_modifier')]
    public function modifier(int $moduleId, Chapitre $chapitre, Request $request, EntityManagerInterface $em): Response
    {
        $module = $this->getModule($moduleId, $em);
        if ($chapitre->getModule()->getId() !== $module->getId()) throw $this->createNotFoundException();
        if ($request->isMethod('POST')) {
            $titre = $request->request->get('titre'); $contenu = $request->request->get('contenu');
            if (empty($titre) || empty($contenu)) {
                $this->addFlash('error', 'Le titre et le contenu sont obligatoires.');
            } else {
                $chapitre->setTitre($titre)->setContenu($contenu)
                    ->setUrlVideo($request->request->get('url_video') ?: null)
                    ->setDureeVideo($request->request->get('duree_video') ? (int)$request->request->get('duree_video') : null);
                $em->flush();
                $this->addFlash('success', ' Chapitre modifié !');
                return $this->redirectToRoute('admin_chapitres_liste', ['moduleId' => $moduleId]);
            }
        }
        return $this->render('admin/chapitres/modifier.html.twig', ['module' => $module, 'chapitre' => $chapitre]);
    }

    #[Route('/{id}/quiz', name: 'admin_chapitre_quiz')]
    public function quiz(int $moduleId, Chapitre $chapitre, Request $request, EntityManagerInterface $em): Response
    {
        $module = $this->getModule($moduleId, $em);
        if ($chapitre->getModule()->getId() !== $module->getId()) throw $this->createNotFoundException();

        $quiz = $chapitre->getQuiz();

        if (!$quiz && $request->query->get('creer') === '1') {
            $quiz = new Quiz();
            $quiz->setTitre('Quiz — ' . $chapitre->getTitre())
                ->setScoreMinimum(70)->setNombreTentativesMax(3)
                ->setChapitre($chapitre);
            $em->persist($quiz);
            $em->flush();
            $this->addFlash('success', ' Quiz créé !');
            return $this->redirectToRoute('admin_chapitre_quiz', ['moduleId' => $moduleId, 'id' => $chapitre->getId()]);
        }

        if ($request->isMethod('POST')){
            $action = $request->request->get('action', 'ajouter_question');

            if ($action === 'modifier_parametres') {
                if ($quiz) {
                    $quiz->setScoreMinimum((int)$request->request->get('score_minimum', 70))
                        ->setNombreTentativesMax((int)$request->request->get('tentatives_max', 3));
                    $em->flush();
                    $this->addFlash('success', 'Paramètres mis à jour !');
                }
            } elseif ($quiz) {
                $questionTexte     = trim($request->request->get('question', ''));
                $typeQuestion      = $request->request->get('type_question', 'QCM');
                $options           = array_values(array_filter(array_map('trim', $request->request->all('options'))));
                $reponsesCorrectes = array_values(array_filter($request->request->all('reponses_correctes')));
                $explication       = trim($request->request->get('explication', ''));
                $points            = (int)$request->request->get('points', 10);

                if (empty($questionTexte)) {
                    $this->addFlash('error', 'La question est obligatoire.');
                } elseif (count($options) < 2) {
                    $this->addFlash('error', 'Au moins 2 options sont nécessaires.');
                } elseif (empty($reponsesCorrectes)) {
                    $this->addFlash('error', 'Sélectionnez au moins une bonne réponse.');
                } else {
                    $newQuestion = [
                        'question'          => $questionTexte,
                        'type_question'     => $typeQuestion,
                        'options'           => $options,
                        'reponses_correctes' => $reponsesCorrectes,
                        'points'            => $points,
                        'explication'       => $explication ?: null,
                    ];
                    $questions = $quiz->getQuestions() ?? [];
                    $questions[] = $newQuestion;
                    $quiz->setQuestions($questions);
                    $em->flush();
                    $this->addFlash('success', ' Question ajoutée !');
                }
            }
            return $this->redirectToRoute('admin_chapitre_quiz', ['moduleId' => $moduleId, 'id' => $chapitre->getId()]);
        }

        return $this->render('admin/chapitres/quiz.html.twig', [
            'module'   => $module,
            'chapitre' => $chapitre,
            'quiz'     => $quiz,
        ]);
    }

    #[Route('/{id}/quiz/question/{index}/supprimer', name: 'admin_chapitre_quiz_question_supprimer', methods: ['POST'])]
    public function supprimerQuestion(int $moduleId, Chapitre $chapitre, int $index, EntityManagerInterface $em): Response
    {
        $quiz = $chapitre->getQuiz();
        if ($quiz) {
            $questions = $quiz->getQuestions() ?? [];
            if (isset($questions[$index])) {
                array_splice($questions, $index, 1);
                $quiz->setQuestions($questions);
                $em->flush();
                $this->addFlash('success', 'Question supprimée.');
            } else {
                $this->addFlash('error', 'Question introuvable.');
            }
        } else {
            $this->addFlash('error', 'Quiz introuvable.');
        }
        return $this->redirectToRoute('admin_chapitre_quiz', ['moduleId' => $moduleId, 'id' => $chapitre->getId()]);
    }

    #[Route('/{id}/supprimer', name: 'admin_chapitre_supprimer', methods: ['POST'])]
    public function supprimer(int $moduleId, Chapitre $chapitre, EntityManagerInterface $em): Response
    {
        if ($chapitre->getModule()->getId() !== $moduleId) throw $this->createNotFoundException();
        $em->remove($chapitre);
        $em->flush();
        $this->addFlash('success', 'Chapitre supprimé !');
        return $this->redirectToRoute('admin_chapitres_liste', ['moduleId' => $moduleId]);
    }
}