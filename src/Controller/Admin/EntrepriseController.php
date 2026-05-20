<?php

namespace App\Controller\Admin;

use App\Entity\Administrateur;
use App\Entity\Entreprise;
use App\Entity\RSSI;
use App\Service\EmailService;
use App\Service\EmailTemplateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/entreprises')]
#[IsGranted('ROLE_ADMIN')]
class EntrepriseController extends AbstractController
{
    #[Route('', name: 'admin_entreprises_liste')]
    public function liste(EntityManagerInterface $em): Response
    {
        return $this->render('admin/entreprises/liste.html.twig', [
            'entreprises' => $em->getRepository(Entreprise::class)->findAll(),
        ]);
    }

    #[Route('/en-attente', name: 'admin_entreprises_en_attente')]
    public function enAttente(EntityManagerInterface $em): Response
    {
        return $this->render('admin/entreprises/en_attente.html.twig', [
            'entreprises' => $em->getRepository(Entreprise::class)->findEnAttente(),
        ]);
    }

    #[Route('/{id}/valider', name: 'admin_entreprise_valider', methods: ['POST'])]
    public function valider(
        Entreprise $entreprise,
        EntityManagerInterface $em,
        EmailService $emailService
    ): Response {
        if ($entreprise->getStatut() !== 'EN_ATTENTE') {
            $this->addFlash('error', 'Cette entreprise a déjà été traitée.');
            return $this->redirectToRoute('admin_entreprises_en_attente');
        }

        $rssi = $em->getRepository(RSSI::class)->findOneBy(['entreprise' => $entreprise]);
        if (!$rssi) {
            $this->addFlash('error', 'RSSI introuvable pour cette entreprise.');
            return $this->redirectToRoute('admin_entreprises_en_attente');
        }

        // Traçabilité : enregistrer quel admin a validé
        /** @var Administrateur $admin */
        $admin = $this->getUser();
        $entreprise->valider($admin instanceof Administrateur ? $admin : null);

        $rssi->genererJetonActivation();
        $em->flush();

        $lienActivation = $this->generateUrl(
            'app_activation',
            ['token' => $rssi->getJetonActivation()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        try {
            $emailService->envoyerEmailLeitime(
                $rssi->getEmail(),
                'Votre compte SAT Platform est prêt — Activez-le maintenant',
                EmailTemplateService::activationCompte(
                    $rssi->getPrenom(),
                    $rssi->getNom(),
                    $entreprise->getNom(),
                    $lienActivation
                )
            );
            $this->addFlash('success', "Entreprise validée. Email d'activation envoyé à {$rssi->getEmail()}.");
        } catch (\Exception $e) {
            $this->addFlash('warning', "Entreprise validée mais erreur d'envoi email : " . $e->getMessage());
        }

        return $this->redirectToRoute('admin_entreprises_en_attente');
    }

    /**
     * GET  → affiche le formulaire de motif de rejet
     * POST → traite le rejet, envoie l'email avec motif, supprime entreprise + RSSI (CASCADE)
     */
    #[Route('/{id}/rejeter', name: 'admin_entreprise_rejeter', methods: ['GET', 'POST'])]
    public function rejeter(
        Entreprise $entreprise,
        Request $request,
        EntityManagerInterface $em,
        EmailService $emailService
    ): Response {
        if ($entreprise->getStatut() !== 'EN_ATTENTE') {
            $this->addFlash('error', 'Cette entreprise a déjà été traitée.');
            return $this->redirectToRoute('admin_entreprises_en_attente');
        }

        // GET → afficher le formulaire de motif
        if ($request->isMethod('GET')) {
            return $this->render('admin/entreprises/rejeter.html.twig', [
                'entreprise' => $entreprise,
            ]);
        }

        // POST → traiter le rejet
        $motif = trim($request->request->get('motif', ''));

        // Récupérer le RSSI via la relation inverse
        $rssi = $entreprise->getRssis()->first() ?: null;

        if ($rssi) {
            try {
                $emailService->envoyerEmailLeitime(
                    $rssi->getEmail(),
                    "Suite à votre demande d'inscription — SAT Platform",
                    EmailTemplateService::inscriptionRejetee(
                        $entreprise->getNom(),
                        $rssi->getEmail(),
                        $motif ?: null
                    )
                );
            } catch (\Exception $e) {
                $this->addFlash('warning', "Erreur d'envoi email : " . $e->getMessage());
            }
        }

        // Suppression de l'entreprise → CASCADE supprime automatiquement le(s) RSSI
        $em->remove($entreprise);
        $em->flush();

        $this->addFlash('success', 'Entreprise rejetée avec succès.');
        return $this->redirectToRoute('admin_entreprises_en_attente');
    }

    #[Route('/{id}/suspendre', name: 'admin_entreprise_suspendre', methods: ['POST'])]
    public function suspendre(Entreprise $entreprise, EntityManagerInterface $em): Response
    {
        $entreprise->suspendre();
        $em->flush();
        $this->addFlash('success', 'Entreprise suspendue.');
        return $this->redirectToRoute('admin_entreprises_liste');
    }

    #[Route('/{id}/reactiver', name: 'admin_entreprise_reactiver', methods: ['POST'])]
    public function reactiver(Entreprise $entreprise, EntityManagerInterface $em): Response
    {
        $entreprise->reactiver();
        $em->flush();
        $this->addFlash('success', 'Entreprise réactivée.');
        return $this->redirectToRoute('admin_entreprises_liste');
    }

    #[Route('/{id}', name: 'admin_entreprise_details')]
    public function details(Entreprise $entreprise, EntityManagerInterface $em): Response
    {
        // Via la relation inverse — plus besoin de chercher par email
        $rssi = $entreprise->getRssis()->first() ?: null;

        $employes = $em->createQueryBuilder()
            ->select('e')->from(\App\Entity\Employe::class, 'e')
            ->join('e.departement', 'd')
            ->where('d.entreprise = :entreprise')
            ->setParameter('entreprise', $entreprise)
            ->getQuery()->getResult();

        $totalEmployes  = count($employes);
        $employesActifs = 0;
        $totalScore     = 0;
        $nbScore        = 0;

        foreach ($employes as $e) {
            if ($e->isEstActif()) $employesActifs++;
            if ($e->getTotalPoints() > 0) {
                $totalScore += $e->getScoreVigilance();
                $nbScore++;
            }
        }

        return $this->render('admin/entreprises/details.html.twig', [
            'entreprise'     => $entreprise,
            'rssi'           => $rssi,
            'employes'       => $employes,
            'totalEmployes'  => $totalEmployes,
            'employesActifs' => $employesActifs,
            'scoreMoyen'     => $nbScore > 0 ? round($totalScore / $nbScore, 1) : 0,
        ]);
    }
}