<?php

namespace App\EventSubscriber;

use App\Entity\RSSI;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class CharteSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private RouterInterface $router
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 5],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Ignorer les sous-requêtes
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route   = $request->attributes->get('_route');

        // Ne pas intercepter ces routes (éviter boucle infinie)
        $routesLibres = [
            'rssi_charte',
            'app_login',
            'app_logout',
            'app_register',
            'app_activation',
            'app_redirect_dashboard',
            '_wdt',       // Symfony toolbar
            '_profiler',  // Symfony profiler
        ];

        if (in_array($route, $routesLibres)) {
            return;
        }

        // Ignorer les routes non-RSSI
        if (!$route || !str_starts_with($route, 'rssi_')) {
            return;
        }

        // Récupérer l'utilisateur connecté
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof RSSI) {
            return;
        }

        // Vérifier si la charte a été acceptée
        $entreprise = $user->getEntreprise();
        if (!$entreprise) {
            return;
        }

        if (!$entreprise->isCharteAcceptee()) {
            // Redirection PHP pure — avant tout rendu de page
            $url = $this->router->generate('rssi_charte');
            $event->setResponse(new RedirectResponse($url));
        }
    }
}