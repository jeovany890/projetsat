<?php

namespace App\EventSubscriber;

use App\Entity\Employe;
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
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route   = $request->attributes->get('_route');

        // Routes libres — jamais interceptées
        $routesLibres = [
            'app_login',
            'app_logout',
            'app_register',
            'app_activation',
            'app_redirect_dashboard',
            'app_2fa',
            'rssi_charte',
            'employe_premiere_connexion',
            '_wdt',
            '_profiler',
        ];

        if (!$route || in_array($route, $routesLibres)) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return;
        }

        $user = $token->getUser();

        // ── Employé : première connexion → forcer changement mot de passe ──
        if ($user instanceof Employe && str_starts_with($route, 'employe_')) {
            if ($user->isEstPremiereConnexion()) {
                $url = $this->router->generate('employe_premiere_connexion');
                $event->setResponse(new RedirectResponse($url));
                return;
            }
        }

        // ── RSSI : charte non acceptée → forcer la charte ──
        if ($user instanceof RSSI && str_starts_with($route, 'rssi_')) {
            $entreprise = $user->getEntreprise();
            if (!$entreprise) {
                return;
            }
            if (!$entreprise->isCharteAcceptee()) {
                $url = $this->router->generate('rssi_charte');
                $event->setResponse(new RedirectResponse($url));
                return;
            }
        }
    }
}