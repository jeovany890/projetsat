<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\EmailService;
class TestController extends AbstractController
{
    #[Route('/test', name: 'app_test')]
    public function index(): Response
    {
        return $this->render('test/index.html.twig');
    }


 #[Route('/test-phpmailer', name: 'app_test_phpmailer')]
    public function testPhpMailer(EmailService $emailService): Response
    {
        // Test email légitime
        $resultat = $emailService->envoyerEmailLeitime(
            'woroujeovany529@gmail.com',  // ← Mets ton email ici
            'Test PHPMailer - SAT Platform',
            '<h1>✅ PHPMailer fonctionne !</h1><p>Le système d\'email est opérationnel.</p>'
        );

        if ($resultat) {
            $this->addFlash('success', '✅ Email envoyé avec succès ! Vérifie ta boîte.');
        } else {
            $this->addFlash('danger', '❌ Erreur lors de l\'envoi. Vérifie les logs.');
        }

        return $this->redirectToRoute('app_test');
    }



}