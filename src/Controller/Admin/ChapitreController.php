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
    #[Route('', name: 'admin_chapitres_liste')]
    public function liste(int $moduleId, EntityManagerInterface $em): Response
    {
        $module = $em->getRepository(ModuleFormation::class)->find($moduleId);
        
        if (!$module) {
            throw $this->createNotFoundException('Module non trouvé');
        }

        $chapitres = $em->getRepository(Chapitre::class)->findBy(
            ['module' => $module],
            ['ordre' => 'ASC']
        );
        
        return $this->render('admin/chapitres/liste.html.twig', [
            'module' => $module,
            'chapitres' => $chapitres,
        ]);
    }

    #[Route('/nouveau', name: 'admin_chapitre_nouveau')]
    public function nouveau(
        int $moduleId,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $module = $em->getRepository(ModuleFormation::class)->find($moduleId);
        
        if (!$module) {
            throw $this->createNotFoundException('Module non trouvé');
        }

        if ($request->isMethod('POST')) {
            $errors = [];

            $titre = $request->request->get('titre');
            $contenu = $request->request->get('contenu');
            $urlVideo = $request->request->get('url_video');
            $dureeVideo = $request->request->get('duree_video');

            if (empty($titre) || empty($contenu)) {
                $errors[] = 'Le titre et le contenu sont obligatoires.';
            }

            if (empty($errors)) {
                // Déterminer l'ordre (dernier + 1)
                $dernierOrdre = $em->createQueryBuilder()
                    ->select('MAX(c.ordre)')
                    ->from(Chapitre::class, 'c')
                    ->where('c.module = :module')
                    ->setParameter('module', $module)
                    ->getQuery()
                    ->getSingleScalarResult();

                $chapitre = new Chapitre();
                $chapitre->setTitre($titre);
                $chapitre->setContenu($contenu);
                $chapitre->setUrlVideo($urlVideo);
                $chapitre->setDureeVideo($dureeVideo ? (int)$dureeVideo : null);
                $chapitre->setOrdre(($dernierOrdre ?? 0) + 1);
                $chapitre->setDateCreation(new \DateTime());
                $chapitre->setModule($module);

                $em->persist($chapitre);
                $em->flush();

                $this->addFlash('success', '✅ Chapitre créé avec succès !');
                return $this->redirectToRoute('admin_chapitres_liste', ['moduleId' => $moduleId]);
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->render('admin/chapitres/nouveau.html.twig', [
            'module' => $module,
        ]);
    }

    #[Route('/{id}/modifier', name: 'admin_chapitre_modifier')]
    public function modifier(
        int $moduleId,
        Chapitre $chapitre,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $module = $em->getRepository(ModuleFormation::class)->find($moduleId);
        
        if (!$module || $chapitre->getModule()->getId() !== $module->getId()) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            $errors = [];

            $titre = $request->request->get('titre');
            $contenu = $request->request->get('contenu');
            $urlVideo = $request->request->get('url_video');
            $dureeVideo = $request->request->get('duree_video');

            if (empty($titre) || empty($contenu)) {
                $errors[] = 'Le titre et le contenu sont obligatoires.';
            }

            if (empty($errors)) {
                $chapitre->setTitre($titre);
                $chapitre->setContenu($contenu);
                $chapitre->setUrlVideo($urlVideo);
                $chapitre->setDureeVideo($dureeVideo ? (int)$dureeVideo : null);

                $em->flush();

                $this->addFlash('success', '✅ Chapitre modifié avec succès !');
                return $this->redirectToRoute('admin_chapitres_liste', ['moduleId' => $moduleId]);
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->render('admin/chapitres/modifier.html.twig', [
            'module' => $module,
            'chapitre' => $chapitre,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'admin_chapitre_supprimer', methods: ['POST'])]
    public function supprimer(
        int $moduleId,
        Chapitre $chapitre,
        EntityManagerInterface $em
    ): Response {
        if ($chapitre->getModule()->getId() !== $moduleId) {
            throw $this->createNotFoundException();
        }

        $em->remove($chapitre);
        $em->flush();

        $this->addFlash('success', '✅ Chapitre supprimé !');
        return $this->redirectToRoute('admin_chapitres_liste', ['moduleId' => $moduleId]);
    }

    #[Route('/{id}/monter', name: 'admin_chapitre_monter', methods: ['POST'])]
    public function monter(
        int $moduleId,
        Chapitre $chapitre,
        EntityManagerInterface $em
    ): Response {
        if ($chapitre->getModule()->getId() !== $moduleId || $chapitre->getOrdre() <= 1) {
            return $this->redirectToRoute('admin_chapitres_liste', ['moduleId' => $moduleId]);
        }

        // Trouver le chapitre précédent
        $chapitreAvant = $em->getRepository(Chapitre::class)->findOneBy([
            'module' => $chapitre->getModule(),
            'ordre' => $chapitre->getOrdre() - 1
        ]);

        if ($chapitreAvant) {
            $ordreTemp = $chapitre->getOrdre();
            $chapitre->setOrdre($chapitreAvant->getOrdre());
            $chapitreAvant->setOrdre($ordreTemp);
            $em->flush();
        }

        return $this->redirectToRoute('admin_chapitres_liste', ['moduleId' => $moduleId]);
    }

    #[Route('/{id}/descendre', name: 'admin_chapitre_descendre', methods: ['POST'])]
    public function descendre(
        int $moduleId,
        Chapitre $chapitre,
        EntityManagerInterface $em
    ): Response {
        if ($chapitre->getModule()->getId() !== $moduleId) {
            return $this->redirectToRoute('admin_chapitres_liste', ['moduleId' => $moduleId]);
        }

        // Trouver le chapitre suivant
        $chapitreApres = $em->getRepository(Chapitre::class)->findOneBy([
            'module' => $chapitre->getModule(),
            'ordre' => $chapitre->getOrdre() + 1
        ]);

        if ($chapitreApres) {
            $ordreTemp = $chapitre->getOrdre();
            $chapitre->setOrdre($chapitreApres->getOrdre());
            $chapitreApres->setOrdre($ordreTemp);
            $em->flush();
        }

        return $this->redirectToRoute('admin_chapitres_liste', ['moduleId' => $moduleId]);
    }
}