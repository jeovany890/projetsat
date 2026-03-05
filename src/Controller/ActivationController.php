<?php

namespace App\Controller;

use App\Entity\RSSI;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class ActivationController extends AbstractController
{
    #[Route('/activation/{token}', name: 'app_activation')]
    public function activate(
        string $token,
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        // Trouver le RSSI avec ce token
        $rssi = $entityManager->getRepository(RSSI::class)
            ->findOneBy(['jetonActivation' => $token]);

        if (!$rssi) {
            $this->addFlash('error', 'Lien d\'activation invalide.');
            return $this->redirectToRoute('app_login');
        }

        if (!$rssi->isJetonActivationValide()) {
            $this->addFlash('error', 'Ce lien d\'activation a expiré.');
            return $this->redirectToRoute('app_login');
        }

        if ($request->isMethod('POST')) {
            $password = $request->request->get('password');
            $passwordConfirm = $request->request->get('password_confirm');

            if ($password !== $passwordConfirm) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
            } elseif (strlen($password) < 8) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caractères.');
            } else {
                // Activer le compte
                $hashedPassword = $passwordHasher->hashPassword($rssi, $password);
                $rssi->setMotDePasse($hashedPassword);
                $rssi->setEstActif(true);
                $rssi->setEstVerifie(true);
                $rssi->setJetonActivation(null);
                $rssi->setJetonExpiration(null);
                $rssi->setEstPremiereConnexion(false);

                $entityManager->flush();

                $this->addFlash('success', '✅ Votre compte a été activé avec succès ! Vous pouvez maintenant vous connecter.');
                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/activation.html.twig', [
            'rssi' => $rssi,
            'token' => $token,
        ]);
    }
}