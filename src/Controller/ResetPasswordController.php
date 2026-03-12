<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Service\EmailService;
use App\Service\EmailTemplateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ResetPasswordController extends AbstractController
{
    #[Route('/reset-password', name: 'app_reset_password_request')]
    public function request(
        Request $request,
        EntityManagerInterface $entityManager,
        EmailService $emailService,
        UrlGeneratorInterface $router
    ): Response {
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $user  = $entityManager->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $user->setResetPasswordToken($token);
                $user->setResetPasswordExpiration((new \DateTime())->modify('+1 hour'));
                $entityManager->flush();

                $resetLink = $router->generate(
                    'app_reset_password_reset',
                    ['token' => $token],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                try {
                    $emailService->envoyerEmailLeitime(
                        $user->getEmail(),
                        'Réinitialisation de votre mot de passe — SAT Platform',
                        EmailTemplateService::reinitialiserMotDePasse($user->getPrenom(), $resetLink)
                    );
                } catch (\Exception $e) {
                    error_log('Email reset password : ' . $e->getMessage());
                }
            }

            $this->addFlash('success', 'Si cet email existe, vous recevrez un lien de réinitialisation.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password_request.html.twig');
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password_reset')]
    public function reset(
        string $token,
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $user = $entityManager->getRepository(Utilisateur::class)->findOneBy(['resetPasswordToken' => $token]);

        if (!$user || !$user->isTokenResetValide()) {
            $this->addFlash('error', 'Lien de réinitialisation invalide ou expiré.');
            return $this->redirectToRoute('app_reset_password_request');
        }

        if ($request->isMethod('POST')) {
            $password        = $request->request->get('password');
            $passwordConfirm = $request->request->get('password_confirm');

            if ($password !== $passwordConfirm) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
            } elseif (strlen($password) < 8) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caractères.');
            } else {
                $user->setMotDePasse($passwordHasher->hashPassword($user, $password));
                $user->setResetPasswordToken(null);
                $user->setResetPasswordExpiration(null);
                $entityManager->flush();

                $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès.');
                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/reset_password_reset.html.twig', ['token' => $token]);
    }
}