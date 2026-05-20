<?php

namespace App\Controller;

use App\Entity\Employe;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    // ══════════════════════════════════════════
    // LOGIN / LOGOUT
    // ══════════════════════════════════════════
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_redirect_dashboard');
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error'         => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('Intercepted by firewall.');
    }

    // ══════════════════════════════════════════
    // REDIRECTION APRÈS LOGIN
    // ══════════════════════════════════════════
    #[Route('/redirect-dashboard', name: 'app_redirect_dashboard')]
    public function redirectDashboard(Request $request): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Première connexion employé → changer le mot de passe d'abord
        if ($user instanceof Employe && $user->isEstPremiereConnexion()) {
            return $this->redirectToRoute('employe_premiere_connexion');
        }

        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return $this->redirectToRoute('admin_dashboard');
        }

        if (in_array('ROLE_RSSI', $user->getRoles())) {
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

    // ══════════════════════════════════════════
    // PREMIÈRE CONNEXION EMPLOYÉ
    //
    // L'employé change son mot de passe temporaire.
    // Le score de vigilance est déjà à 50 par défaut
    // (défini dans Employe::$scoreVigilance = 50.0).
    // Après changement → dashboard directement.
    // Plus de quiz initial.
    // ══════════════════════════════════════════
    #[Route('/employe/premiere-connexion', name: 'employe_premiere_connexion')]
    public function premiereConnexion(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        /** @var Employe $employe */
        $employe = $this->getUser();

        // Déjà fait → dashboard
        if (!$employe->isEstPremiereConnexion()) {
            return $this->redirectToRoute('employe_dashboard');
        }

        if ($request->isMethod('POST')) {
            $nouveau = $request->request->get('nouveau_mot_de_passe', '');
            $confirm = $request->request->get('confirmation', '');

            if (strlen($nouveau) < 8) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caractères.');
            } elseif ($nouveau !== $confirm) {
                $this->addFlash('error', 'Les deux mots de passe ne correspondent pas.');
            } else {
                $employe->setMotDePasse($hasher->hashPassword($employe, $nouveau));
                $employe->setEstPremiereConnexion(false);

                // S'assurer que le score de vigilance initial est bien à 50
                // (cas où l'entité aurait une valeur différente)
                if ($employe->getScoreVigilance() === 0.0) {
                    $employe->setScoreVigilance(50.0);
                }

                $em->flush();

                $this->addFlash('success', 'Bienvenue ! Votre compte est maintenant actif.');
                return $this->redirectToRoute('employe_dashboard');
            }
        }

        return $this->render('security/premiere_connexion.html.twig', [
            'employe' => $employe,
        ]);
    }
}