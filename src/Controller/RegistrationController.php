<?php

namespace App\Controller;

use App\Entity\Entreprise;
use App\Entity\RSSI;
use App\Entity\Administrateur;
use App\Service\EmailService;
use App\Service\EmailTemplateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        EmailService $emailService,
        UrlGeneratorInterface $router
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_redirect_dashboard');
        }

        if ($request->isMethod('POST')) {
            $errors = [];

            $entrepriseNom       = $request->request->get('entreprise_nom');
            $ifu                 = $request->request->get('ifu');
            $rccm                = $request->request->get('rccm');
            $secteur             = $request->request->get('secteur');
            $nombreEmployes      = $request->request->get('nombre_employes');
            $telephoneEntreprise = $request->request->get('telephone_entreprise');
            $emailEntreprise     = $request->request->get('email_entreprise');
            $adresse             = $request->request->get('adresse');
            $prenomRssi          = $request->request->get('prenom_rssi');
            $nomRssi             = $request->request->get('nom_rssi');
            $emailRssi           = $request->request->get('email_rssi');
            $telephoneRssi       = $request->request->get('telephone_rssi');

            if (empty($entrepriseNom) || empty($ifu) || empty($emailRssi)) {
                $errors[] = 'Tous les champs obligatoires doivent être remplis.';
            }

            if ($entityManager->getRepository(Entreprise::class)->findOneBy(['ifu' => $ifu])) {
                $errors[] = 'Une entreprise avec cet IFU existe déjà.';
            }

            if ($entityManager->getRepository(RSSI::class)->findOneBy(['email' => $emailRssi])) {
                $errors[] = 'Cet email est déjà utilisé.';
            }

            if (empty($errors)) {
                $entreprise = new Entreprise();
                $entreprise->setNom($entrepriseNom)->setIfu($ifu)->setRccm($rccm)
                    ->setSecteur($secteur)->setNombreEmployes((int)$nombreEmployes)
                    ->setTelephone($telephoneEntreprise)->setEmail($emailEntreprise)
                    ->setAdresse($adresse)->setStatut('EN_ATTENTE');
                $entityManager->persist($entreprise);

                $rssi = new RSSI();
                $rssi->setPrenom($prenomRssi)->setNom($nomRssi)->setEmail($emailRssi)
                    ->setTelephone($telephoneRssi)->setEstActif(false)->setEstVerifie(false)
                    ->setEntreprise($entreprise);
                $tempPassword = bin2hex(random_bytes(8));
                $rssi->setMotDePasse($passwordHasher->hashPassword($rssi, $tempPassword));
                $entityManager->persist($rssi);
                $entityManager->flush();

                // Email au RSSI
                try {
                    $emailService->envoyerEmailLeitime(
                        $emailRssi,
                        'Votre demande d\'inscription — SAT Platform',
                        EmailTemplateService::inscriptionRecue(
                            $prenomRssi, $nomRssi, $entrepriseNom, $ifu, $secteur, $nombreEmployes
                        )
                    );
                } catch (\Exception $e) {
                    error_log('Email inscription RSSI : ' . $e->getMessage());
                }

                // Email à l'admin
                try {
                    $admin = $entityManager->getRepository(Administrateur::class)->findOneBy([]);
                    if ($admin) {
                        $urlAdmin = $router->generate('admin_entreprises_en_attente', [], UrlGeneratorInterface::ABSOLUTE_URL);
                        $emailService->envoyerEmailLeitime(
                            $admin->getEmail(),
                            'Nouvelle demande d\'inscription — SAT Platform',
                            EmailTemplateService::nouvelleInscriptionAdmin(
                                $entrepriseNom, $ifu, $rccm, $secteur, $nombreEmployes,
                                $emailEntreprise, $telephoneEntreprise,
                                $prenomRssi, $nomRssi, $emailRssi, $telephoneRssi,
                                $urlAdmin
                            )
                        );
                    }
                } catch (\Exception $e) {
                    error_log('Email inscription admin : ' . $e->getMessage());
                }

                $this->addFlash('success', 'Votre demande a été enregistrée. Vous recevrez un email de confirmation sous 48h.');
                return $this->redirectToRoute('app_login');
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->render('security/register.html.twig');
    }
}