<?php

namespace App\Controller\Admin;

use App\Entity\Chapitre;
use App\Entity\ModuleFormation;
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
        $module = $this->getModule($moduleId, $em);
        $chapitres = $em->getRepository(Chapitre::class)->findBy(['module' => $module], ['id' => 'ASC']);
        return $this->render('admin/chapitres/liste.html.twig', [
            'module' => $module,
            'chapitres' => $chapitres,
        ]);
    }

    #[Route('/nouveau', name: 'admin_chapitre_nouveau', methods: ['GET', 'POST'])]
    public function nouveau(int $moduleId, Request $request, EntityManagerInterface $em): Response
    {
        $module = $this->getModule($moduleId, $em);
        if ($request->isMethod('POST')) {
            $titre = $request->request->get('titre');
            $contenu = $request->request->get('contenu');
            if (!$titre || !$contenu) {
                $this->addFlash('error', 'Le titre et le contenu sont obligatoires.');
                return $this->redirectToRoute('admin_chapitre_nouveau', ['moduleId' => $moduleId]);
            }

            $chapitre = new Chapitre();
            $chapitre->setTitre($titre);
            $chapitre->setContenu($contenu);
            $chapitre->setUrlVideo($request->request->get('url_video') ?: null);
            $chapitre->setDureeVideo($request->request->get('duree_video') ? (int)$request->request->get('duree_video') : null);
            $chapitre->setModule($module);

            // Quiz intégré
            $chapitre->setQuizNombreTentativesMax($request->request->get('quiz_nombre_tentatives_max') ? (int)$request->request->get('quiz_nombre_tentatives_max') : 3);
            $chapitre->setQuizScoreMinimum($request->request->get('quiz_score_minimum') ? (int)$request->request->get('quiz_score_minimum') : 70);
            
            $quizQuestionsJson = $request->request->get('quiz_questions_json');
            if ($quizQuestionsJson) {
                $questions = json_decode($quizQuestionsJson, true);
                if (is_array($questions)) {
                    $chapitre->setQuizQuestions($questions);
                }
            }

            $em->persist($chapitre);
            $em->flush();
            $this->addFlash('success', '✅ Chapitre créé !');
            return $this->redirectToRoute('admin_chapitres_liste', ['moduleId' => $moduleId]);
        }

        return $this->render('admin/chapitres/nouveau.html.twig', ['module' => $module]);
    }

    #[Route('/{id}/modifier', name: 'admin_chapitre_modifier', methods: ['GET', 'POST'])]
    public function modifier(int $moduleId, Chapitre $chapitre, Request $request, EntityManagerInterface $em): Response
    {
        $module = $this->getModule($moduleId, $em);
        if ($chapitre->getModule()->getId() !== $module->getId()) {
            throw $this->createNotFoundException();
        }
        if ($request->isMethod('POST')) {
            $titre = $request->request->get('titre');
            $contenu = $request->request->get('contenu');
            if (!$titre || !$contenu) {
                $this->addFlash('error', 'Le titre et le contenu sont obligatoires.');
                return $this->redirectToRoute('admin_chapitre_modifier', ['moduleId' => $moduleId, 'id' => $chapitre->getId()]);
            }
            $chapitre->setTitre($titre);
            $chapitre->setContenu($contenu);
            $chapitre->setUrlVideo($request->request->get('url_video') ?: null);
            $chapitre->setDureeVideo($request->request->get('duree_video') ? (int)$request->request->get('duree_video') : null);
            
            // Quiz intégré - CORRIGÉ
            $chapitre->setQuizNombreTentativesMax($request->request->get('quiz_nombre_tentatives_max') ? (int)$request->request->get('quiz_nombre_tentatives_max') : 3);
            $chapitre->setQuizScoreMinimum($request->request->get('quiz_score_minimum') ? (int)$request->request->get('quiz_score_minimum') : 70);
            
            // Gestion des questions du quiz
            $quizQuestionsJson = $request->request->get('quiz_questions_json');
            if ($quizQuestionsJson) {
                $questions = json_decode($quizQuestionsJson, true);
                if (is_array($questions) && count($questions) > 0) {
                    $chapitre->setQuizQuestions($questions);
                } else {
                    // Si le JSON est vide ou invalide, on garde les questions existantes
                    // ou on les vide si elles étaient vides
                }
            }
            
            $em->flush();
            $this->addFlash('success', '✅ Chapitre modifié !');
            return $this->redirectToRoute('admin_chapitres_liste', ['moduleId' => $moduleId]);
        }

        return $this->render('admin/chapitres/modifier.html.twig', [
            'module' => $module,
            'chapitre' => $chapitre,
        ]);
    }

    #[Route('/{id}/details', name: 'admin_chapitre_details')]
public function details(int $moduleId, Chapitre $chapitre, EntityManagerInterface $em): Response
{
    $module = $this->getModule($moduleId, $em);
    if ($chapitre->getModule()->getId() !== $module->getId()) {
        throw $this->createNotFoundException();
    }
    return $this->render('admin/chapitres/details.html.twig', [
        'module' => $module,
        'chapitre' => $chapitre,
    ]);
}

    #[Route('/{id}/supprimer', name: 'admin_chapitre_supprimer', methods: ['POST'])]
    public function supprimer(int $moduleId, Chapitre $chapitre, EntityManagerInterface $em): Response
    {
        if ($chapitre->getModule()->getId() !== $moduleId) {
            throw $this->createNotFoundException();
        }
        $em->remove($chapitre);
        $em->flush();
        $this->addFlash('success', 'Chapitre supprimé !');
        return $this->redirectToRoute('admin_chapitres_liste', ['moduleId' => $moduleId]);
    }
}