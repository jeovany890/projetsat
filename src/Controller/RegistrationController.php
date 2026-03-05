<?php

namespace App\Controller;

use App\Entity\Entreprise;
use App\Entity\RSSI;
use App\Entity\Administrateur;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        EmailService $emailService
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_redirect_dashboard');
        }

        if ($request->isMethod('POST')) {
            $errors = [];

            // Données entreprise
            $entrepriseNom = $request->request->get('entreprise_nom');
            $ifu = $request->request->get('ifu');
            $rccm = $request->request->get('rccm');
            $secteur = $request->request->get('secteur');
            $nombreEmployes = $request->request->get('nombre_employes');
            $telephoneEntreprise = $request->request->get('telephone_entreprise');
            $emailEntreprise = $request->request->get('email_entreprise');
            $adresse = $request->request->get('adresse');

            // Données RSSI
            $prenomRssi = $request->request->get('prenom_rssi');
            $nomRssi = $request->request->get('nom_rssi');
            $emailRssi = $request->request->get('email_rssi');
            $telephoneRssi = $request->request->get('telephone_rssi');

            // Validations
            if (empty($entrepriseNom) || empty($ifu) || empty($emailRssi)) {
                $errors[] = 'Tous les champs obligatoires doivent être remplis.';
            }

            // Vérifier IFU unique
            $existingEntreprise = $entityManager->getRepository(Entreprise::class)
                ->findOneBy(['ifu' => $ifu]);
            if ($existingEntreprise) {
                $errors[] = 'Une entreprise avec cet IFU existe déjà.';
            }

            // Vérifier email RSSI unique
            $existingRssi = $entityManager->getRepository(RSSI::class)
                ->findOneBy(['email' => $emailRssi]);
            if ($existingRssi) {
                $errors[] = 'Cet email est déjà utilisé.';
            }

            if (empty($errors)) {
                // Créer l'entreprise
                $entreprise = new Entreprise();
                $entreprise->setNom($entrepriseNom);
                $entreprise->setIfu($ifu);
                $entreprise->setRccm($rccm);
                $entreprise->setSecteur($secteur);
                $entreprise->setNombreEmployes((int)$nombreEmployes);
                $entreprise->setTelephone($telephoneEntreprise);
                $entreprise->setEmail($emailEntreprise);
                $entreprise->setAdresse($adresse);
                $entreprise->setStatut('EN_ATTENTE');

                $entityManager->persist($entreprise);

                // Créer le RSSI (non actif)
                $rssi = new RSSI();
                $rssi->setPrenom($prenomRssi);
                $rssi->setNom($nomRssi);
                $rssi->setEmail($emailRssi);
                $rssi->setTelephone($telephoneRssi);
                $rssi->setEstActif(false);
                $rssi->setEstVerifie(false);
                
                // Mot de passe temporaire
                $tempPassword = bin2hex(random_bytes(8));
                $hashedPassword = $passwordHasher->hashPassword($rssi, $tempPassword);
                $rssi->setMotDePasse($hashedPassword);

                $entityManager->persist($rssi);
                $entityManager->flush();

                // ========================================
                // 📧 ENVOYER EMAIL AU RSSI
                // ========================================
                try {
                    $emailService->envoyerEmailLeitime(
                        $emailRssi,
                        '🎉 Demande d\'inscription enregistrée - SAT Platform',
                        "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                            <div style='background: linear-gradient(135deg, #6366F1 0%, #EC4899 100%); padding: 30px; text-align: center; border-radius: 12px 12px 0 0;'>
                                <h1 style='color: white; margin: 0; font-size: 28px;'>SAT Platform</h1>
                            </div>
                            <div style='background: white; padding: 40px; border-radius: 0 0 12px 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                                <h2 style='color: #0F172A; margin-top: 0;'>Demande d'inscription enregistrée ✅</h2>
                                <p style='color: #475569; font-size: 16px; line-height: 1.6;'>
                                    Bonjour <strong>{$prenomRssi} {$nomRssi}</strong>,
                                </p>
                                <p style='color: #475569; font-size: 16px; line-height: 1.6;'>
                                    Nous avons bien reçu votre demande d'inscription pour <strong>{$entrepriseNom}</strong>.
                                </p>
                                <div style='background: #EEF2FF; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #6366F1;'>
                                    <p style='margin: 0; color: #4338CA;'>
                                        <strong>📋 Prochaines étapes :</strong><br>
                                        1. Notre équipe va vérifier votre demande (sous 48h)<br>
                                        2. Vous recevrez un email d'activation une fois validée<br>
                                        3. Vous pourrez alors créer votre mot de passe et accéder à la plateforme
                                    </p>
                                </div>
                                <p style='color: #475569; font-size: 14px;'>
                                    <strong>Informations de votre demande :</strong><br>
                                    • Entreprise : {$entrepriseNom}<br>
                                    • IFU : {$ifu}<br>
                                    • Secteur : {$secteur}<br>
                                    • Nombre d'employés : {$nombreEmployes}
                                </p>
                                <p style='color: #64748B; font-size: 14px; margin-top: 30px;'>
                                    Vous avez des questions ? Répondez à cet email ou contactez-nous.
                                </p>
                            </div>
                            <div style='text-align: center; padding: 20px; color: #94A3B8; font-size: 12px;'>
                                © 2026 SAT Platform - Formation Cybersécurité
                            </div>
                        </div>
                        "
                    );
                } catch (\Exception $e) {
                    // Log l'erreur mais ne bloque pas l'inscription
                    error_log("Erreur envoi email RSSI : " . $e->getMessage());
                }

                // ========================================
                // 📧 ENVOYER EMAIL À L'ADMIN
                // ========================================
                try {
                    // Récupérer le premier admin
                    $admin = $entityManager->getRepository(Administrateur::class)->findOneBy([]);
                    
                    if ($admin) {
                        $emailService->envoyerEmailLeitime(
                            $admin->getEmail(),
                            '🔔 Nouvelle demande d\'inscription - SAT Platform',
                            "
                            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                                <div style='background: #0F172A; padding: 30px; text-align: center; border-radius: 12px 12px 0 0;'>
                                    <h1 style='color: white; margin: 0; font-size: 28px;'>Nouvelle Demande</h1>
                                </div>
                                <div style='background: white; padding: 40px; border-radius: 0 0 12px 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                                    <h2 style='color: #0F172A; margin-top: 0;'>Une entreprise souhaite rejoindre SAT Platform</h2>
                                    
                                    <div style='background: #FEF3C7; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #F59E0B;'>
                                        <p style='margin: 0; color: #92400E;'>
                                            <strong>⚠️ Action requise :</strong> Validez ou rejetez cette demande dans l'admin.
                                        </p>
                                    </div>

                                    <div style='background: #F1F5F9; padding: 20px; border-radius: 8px;'>
                                        <p style='margin: 0 0 10px 0; color: #0F172A; font-weight: bold;'>📊 Informations entreprise :</p>
                                        <p style='margin: 5px 0; color: #475569;'>• Nom : <strong>{$entrepriseNom}</strong></p>
                                        <p style='margin: 5px 0; color: #475569;'>• IFU : <strong>{$ifu}</strong></p>
                                        <p style='margin: 5px 0; color: #475569;'>• RCCM : <strong>{$rccm}</strong></p>
                                        <p style='margin: 5px 0; color: #475569;'>• Secteur : <strong>{$secteur}</strong></p>
                                        <p style='margin: 5px 0; color: #475569;'>• Employés : <strong>{$nombreEmployes}</strong></p>
                                        <p style='margin: 5px 0; color: #475569;'>• Email : {$emailEntreprise}</p>
                                        <p style='margin: 5px 0; color: #475569;'>• Téléphone : {$telephoneEntreprise}</p>
                                    </div>

                                    <div style='background: #F1F5F9; padding: 20px; border-radius: 8px; margin-top: 20px;'>
                                        <p style='margin: 0 0 10px 0; color: #0F172A; font-weight: bold;'>👤 RSSI désigné :</p>
                                        <p style='margin: 5px 0; color: #475569;'>• Nom : <strong>{$prenomRssi} {$nomRssi}</strong></p>
                                        <p style='margin: 5px 0; color: #475569;'>• Email : {$emailRssi}</p>
                                        <p style='margin: 5px 0; color: #475569;'>• Téléphone : {$telephoneRssi}</p>
                                    </div>

                                    <div style='text-align: center; margin-top: 30px;'>
                                        <a href='http://localhost:8000/admin/entreprises/validation' style='display: inline-block; background: linear-gradient(135deg, #6366F1, #EC4899); color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;'>
                                            📋 Accéder à l'admin
                                        </a>
                                    </div>
                                </div>
                                <div style='text-align: center; padding: 20px; color: #94A3B8; font-size: 12px;'>
                                    © 2026 SAT Platform - Panneau d'administration
                                </div>
                            </div>
                            "
                        );
                    }
                } catch (\Exception $e) {
                    error_log("Erreur envoi email admin : " . $e->getMessage());
                }

                $this->addFlash('success', '🎉 Votre demande a été enregistrée ! Un administrateur la validera sous 48h. Vous recevrez un email d\'activation.');

                return $this->redirectToRoute('app_login');
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->render('security/register.html.twig');
    }
}