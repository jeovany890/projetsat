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
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class EntrepriseStatusSubscriber implements EventSubscriberInterface
{
    private TokenStorageInterface $tokenStorage;
    private RouterInterface $router;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        RouterInterface $router
    ) {
        $this->tokenStorage = $tokenStorage;
        $this->router = $router;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->tokenStorage->getToken();

        if (!$token instanceof UsernamePasswordToken) {
            return;
        }

        $user = $token->getUser();

        if (!is_object($user)) {
            return;
        }

        $entreprise = null;

        if ($user instanceof RSSI) {
            $entreprise = $user->getEntreprise();
        } elseif ($user instanceof Employe) {
            $entreprise = $user->getEntreprise();
        }

        if (!$entreprise || !$entreprise->isSuspendue()) {
            return;
        }

        $request = $event->getRequest();
        $currentRoute = $request->attributes->get('_route');

        if (in_array($currentRoute, ['app_login', 'app_logout'], true)) {
            return;
        }

        // Déconnecter
        $this->tokenStorage->setToken(null);
        $request->getSession()->invalidate();

        // Rediriger vers login avec message dans l'URL
        $url = $this->router->generate('app_login', [
            'error' => 'suspendue'
        ]);

        $event->setResponse(new RedirectResponse($url));
    }
}