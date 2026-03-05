<?php

namespace App\Controller\Admin;

use App\Entity\Entreprise;
use App\Entity\RSSI;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        $entreprises = $em->getRepository(Entreprise::class)->findAll();
        
        return $this->render('admin/entreprises/liste.html.twig', [
            'entreprises' => $entreprises,
        ]);
    }

    #[Route('/en-attente', name: 'admin_entreprises_en_attente')]
    public function enAttente(EntityManagerInterface $em): Response
    {
        $entreprises = $em->getRepository(Entreprise::class)->findEnAttente();
        
        return $this->render('admin/entreprises/en_attente.html.twig', [
            'entreprises' => $entreprises,
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

        // Valider l'entreprise
        $entreprise->valider();
        
        // Trouver le RSSI associé
        $rssi = $em->getRepository(RSSI::class)->findOneBy(['email' => $entreprise->getEmail()]);
        
        if (!$rssi) {
            // Si pas trouvé par email entreprise, chercher autrement
            // (à adapter selon ta logique de liaison RSSI-Entreprise)
            $this->addFlash('error', 'RSSI introuvable pour cette entreprise.');
            return $this->redirectToRoute('admin_entreprises_en_attente');
        }

        // Générer jeton d'activation
        $rssi->genererJetonActivation();
        
        $em->flush();

        // Envoyer email d'activation au RSSI
        $lienActivation = $this->generateUrl(
            'app_activation',
            ['token' => $rssi->getJetonActivation()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        try {
            $emailService->envoyerEmailLeitime(
                $rssi->getEmail(),
                '✅ Votre entreprise a été validée - Activez votre compte',
                "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <div style='background: linear-gradient(135deg, #10B981 0%, #059669 100%); padding: 40px; text-align: center; border-radius: 12px 12px 0 0;'>
                        <h1 style='color: white; margin: 0; font-size: 32px;'>🎉 Félicitations !</h1>
                    </div>
                    <div style='background: white; padding: 40px; border-radius: 0 0 12px 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                        <h2 style='color: #0F172A; margin-top: 0;'>Votre entreprise a été validée</h2>
                        <p style='color: #475569; font-size: 16px; line-height: 1.6;'>
                            Bonjour <strong>{$rssi->getPrenom()} {$rssi->getNom()}</strong>,
                        </p>
                        <p style='color: #475569; font-size: 16px; line-height: 1.6;'>
                            Excellente nouvelle ! Votre demande d'inscription pour <strong>{$entreprise->getNom()}</strong> a été validée par notre équipe.
                        </p>
                        <div style='background: #D1FAE5; padding: 20px; border-radius: 8px; margin: 30px 0; border-left: 4px solid #10B981;'>
                            <p style='margin: 0; color: #065F46;'>
                                <strong>👉 Prochaine étape :</strong> Activez votre compte en cliquant sur le bouton ci-dessous.
                            </p>
                        </div>
                        <div style='text-align: center; margin: 30px 0;'>
                            <a href='{$lienActivation}' style='display: inline-block; background: linear-gradient(135deg, #6366F1, #EC4899); color: white; padding: 16px 40px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 18px;'>
                                🚀 Activer mon compte
                            </a>
                        </div>
                        <p style='color: #64748B; font-size: 14px;'>
                            Ce lien est valide pendant <strong>48 heures</strong>. Si vous n'activez pas votre compte avant cette échéance, vous devrez contacter le support.
                        </p>
                        <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #E2E8F0;'>
                            <p style='color: #64748B; font-size: 14px; margin: 0;'>
                                <strong>Besoin d'aide ?</strong><br>
                                Répondez à cet email ou contactez-nous à support@satplatform.bj
                            </p>
                        </div>
                    </div>
                    <div style='text-align: center; padding: 20px; color: #94A3B8; font-size: 12px;'>
                        © 2026 SAT Platform - Formation Cybersécurité
                    </div>
                </div>
                "
            );

            $this->addFlash('success', "✅ Entreprise validée ! Email d'activation envoyé à {$rssi->getEmail()}");
        } catch (\Exception $e) {
            $this->addFlash('warning', "Entreprise validée mais erreur d'envoi email : " . $e->getMessage());
        }

        return $this->redirectToRoute('admin_entreprises_en_attente');
    }

    #[Route('/{id}/rejeter', name: 'admin_entreprise_rejeter', methods: ['POST'])]
    public function rejeter(
        Entreprise $entreprise,
        EntityManagerInterface $em,
        EmailService $emailService
    ): Response {
        if ($entreprise->getStatut() !== 'EN_ATTENTE') {
            $this->addFlash('error', 'Cette entreprise a déjà été traitée.');
            return $this->redirectToRoute('admin_entreprises_en_attente');
        }

        $entreprise->rejeter();
        $em->flush();

        // Optionnel : Envoyer email de rejet
        try {
            $emailService->envoyerEmailLeitime(
                $entreprise->getEmail(),
                'Votre demande d\'inscription - SAT Platform',
                "
                <p>Bonjour,</p>
                <p>Nous sommes désolés de vous informer que votre demande d'inscription pour <strong>{$entreprise->getNom()}</strong> n'a pas été acceptée.</p>
                <p>Pour plus d'informations, veuillez nous contacter à support@satplatform.bj</p>
                <p>Cordialement,<br>L'équipe SAT Platform</p>
                "
            );
        } catch (\Exception $e) {
            // Log mais ne bloque pas
        }

        $this->addFlash('success', 'Entreprise rejetée.');

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
    public function details(Entreprise $entreprise): Response
    {
        return $this->render('admin/entreprises/details.html.twig', [
            'entreprise' => $entreprise,
        ]);
    }
}