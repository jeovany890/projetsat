<?php

namespace App\Controller;

use App\Entity\RSSI;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_redirect_dashboard');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error'         => $error,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('Intercepted by firewall.');
    }

    #[Route('/redirect-dashboard', name: 'app_redirect_dashboard')]
    public function redirectDashboard(Request $request): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return $this->redirectToRoute('admin_dashboard');
        }

        if (in_array('ROLE_RSSI', $user->getRoles())) {
            // ✅ 2FA obligatoire pour les RSSI
            if (!$request->getSession()->get('2fa_verified')) {
                return $this->redirectToRoute('app_2fa');
            }
            return $this->redirectToRoute('rssi_dashboard');
        }

        if (in_array('ROLE_EMPLOYE', $user->getRoles())) {
            return $this->redirectToRoute('employe_dashboard');
        }

        return $this->redirectToRoute('app_login');
    }
}