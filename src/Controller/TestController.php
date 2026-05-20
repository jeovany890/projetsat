<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class TestController
{
    #[Route('/test-brut', name: 'app_test_brut')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(): Response
    {
        return new Response('Ça marche !');
    }
}