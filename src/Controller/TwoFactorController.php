<?php

namespace App\Controller;

use App\Entity\RSSI;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TwoFactorController extends AbstractController
{
    // ──────────────────────────────────────────────────────
    // PAGE D'ATTENTE — envoie le lien token par email
    // ──────────────────────────────────────────────────────
    #[Route('/2fa', name: 'app_2fa')]
    public function attendre(
        Request $request,
        EntityManagerInterface $em,
        EmailService $emailService
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof RSSI) {
            return $this->redirectToRoute('app_redirect_dashboard');
        }

        if ($request->getSession()->get('2fa_verified')) {
            return $this->redirectToRoute('rssi_dashboard');
        }

        if (!$request->getSession()->get('2fa_sent')) {
            $this->genererEtEnvoyerToken($user, $em, $emailService, $request);
            $request->getSession()->set('2fa_sent', true);
        }

        return $this->render('security/2fa.html.twig', [
            'email' => $this->masquerEmail($user->getEmail()),
        ]);
    }

    // ──────────────────────────────────────────────────────
    // VÉRIFICATION DU TOKEN (clic sur le lien dans l'email)
    // ──────────────────────────────────────────────────────
    #[Route('/2fa/verifier/{token}', name: 'app_2fa_verifier')]
    public function verifier(
        string $token,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof RSSI) {
            return $this->redirectToRoute('app_login');
        }

        if (
            $user->getCodeOtp() === $token &&
            $user->getCodeOtpExpiration() !== null &&
            $user->getCodeOtpExpiration() > new \DateTime()
        ) {
            // Token valide — connexion complète
            $user->setCodeOtp(null);
            $user->setCodeOtpExpiration(null);
            $em->flush();

            $request->getSession()->remove('2fa_sent');
            $request->getSession()->set('2fa_verified', true);

            $this->addFlash('success', 'Connexion réussie. Bienvenue sur votre espace RSSI.');
            return $this->redirectToRoute('rssi_dashboard');
        }

        // Token invalide ou expiré
        $request->getSession()->remove('2fa_sent');
        $this->addFlash('error', 'Ce lien de connexion est invalide ou a expiré. Un nouveau lien vous a été envoyé.');
        return $this->redirectToRoute('app_2fa');
    }

    // ──────────────────────────────────────────────────────
    // RENVOYER UN NOUVEAU LIEN
    // ──────────────────────────────────────────────────────
    #[Route('/2fa/renvoyer', name: 'app_2fa_renvoyer', methods: ['POST'])]
    public function renvoyer(
        Request $request,
        EntityManagerInterface $em,
        EmailService $emailService
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof RSSI) {
            return $this->redirectToRoute('app_login');
        }

        $this->genererEtEnvoyerToken($user, $em, $emailService, $request);
        $request->getSession()->set('2fa_sent', true);

        $this->addFlash('success', 'Un nouveau lien de connexion a été envoyé à votre adresse email.');
        return $this->redirectToRoute('app_2fa');
    }

    // ──────────────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────────────
    private function genererEtEnvoyerToken(
        RSSI $rssi,
        EntityManagerInterface $em,
        EmailService $emailService,
        Request $request
    ): void {
        $token      = bin2hex(random_bytes(32)); // 64 chars hex
        $expiration = new \DateTime('+15 minutes');

        // Réutilisation des colonnes code_otp / code_otp_expiration
        // Pas de migration BDD nécessaire
        $rssi->setCodeOtp($token);
        $rssi->setCodeOtpExpiration($expiration);
        $em->flush();

        $lien = $this->generateUrl(
            'app_2fa_verifier',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $this->envoyerEmailToken($emailService, $rssi, $lien);
    }

    private function envoyerEmailToken(EmailService $emailService, RSSI $rssi, string $lien): void
    {
        $prenom = htmlspecialchars($rssi->getPrenom());
        $html   = "
        <div style='font-family:Arial,sans-serif;max-width:520px;margin:0 auto;'>

            <div style='background:#0B1120;padding:28px 32px;text-align:center;border-radius:12px 12px 0 0;'>
                <div style='font-size:26px;font-weight:900;color:#00E5A0;letter-spacing:-0.5px;'>SAT Platform</div>
                <div style='color:rgba(255,255,255,0.5);font-size:11px;margin-top:4px;text-transform:uppercase;letter-spacing:1px;'>Security Awareness Training</div>
            </div>

            <div style='background:white;padding:36px 32px;'>
                <p style='color:#374151;font-size:15px;margin:0 0 8px;'>Bonjour <strong>{$prenom}</strong>,</p>
                <p style='color:#6B7280;font-size:14px;line-height:1.6;margin:0 0 28px;'>
                    Une connexion à votre espace RSSI a été initiée.
                    Cliquez sur le bouton ci-dessous pour vous connecter.
                    Ce lien est <strong>valable 15 minutes</strong> et ne peut être utilisé qu'une seule fois.
                </p>

                <div style='text-align:center;margin:0 0 28px;'>
                    <a href='{$lien}'
                       style='display:inline-block;background:#0B1120;color:#00E5A0;
                              text-decoration:none;padding:16px 40px;border-radius:10px;
                              font-size:15px;font-weight:700;letter-spacing:0.3px;'>
                        Se connecter &rarr;
                    </a>
                </div>

                <div style='background:#F9FAFB;border:1px solid #E5E7EB;border-radius:8px;padding:14px 16px;margin-bottom:20px;'>
                    <div style='font-size:11px;color:#9CA3AF;margin-bottom:4px;'>Si le bouton ne fonctionne pas, copiez ce lien :</div>
                    <div style='font-size:11px;color:#6B7280;word-break:break-all;font-family:monospace;line-height:1.4;'>{$lien}</div>
                </div>

                <hr style='border:none;border-top:1px solid #F3F4F6;margin:0 0 16px;'>
                <p style='color:#9CA3AF;font-size:12px;line-height:1.5;margin:0;'>
                    Si vous n'êtes pas à l'origine de cette tentative de connexion,
                    ignorez cet email. Votre compte reste sécurisé.
                    Le lien expirera automatiquement dans 15 minutes.
                </p>
            </div>

            <div style='background:#F9FAFB;padding:14px 32px;border-radius:0 0 12px 12px;border-top:1px solid #F3F4F6;text-align:center;'>
                <div style='font-size:11px;color:#9CA3AF;'>SAT Platform &middot; Espace RSSI &middot; Connexion sécurisée</div>
            </div>
        </div>";

        $emailService->envoyerEmailLeitime(
            $rssi->getEmail(),
            'Votre lien de connexion SAT Platform',
            $html,
            'SAT Platform Sécurité'
        );
    }

    private function masquerEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email);
        $masque = substr($local, 0, 2) . str_repeat('*', max(0, strlen($local) - 2));
        return $masque . '@' . $domain;
    }
}