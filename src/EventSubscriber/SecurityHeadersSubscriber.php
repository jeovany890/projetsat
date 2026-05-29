<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onKernelResponse'];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $request  = $event->getRequest();

        // ── Headers de sécurité standard ──────────────────────────────────
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        $response->headers->set('X-XSS-Protection', '0');

        // ── Content Security Policy ────────────────────────────────────────
        //
        //  script-src  : cdn.jsdelivr.net    → Bootstrap JS, Alpine.js, Chart.js
        //                code.iconify.design → Iconify custom element (chargé 1x dans base.html.twig)
        //
        //  style-src   : cdn.jsdelivr.net    → Bootstrap CSS + Bootstrap Icons CSS
        //                fonts.googleapis.com → déclarations @font-face Inter
        //
        //  font-src    : fonts.gstatic.com   → fichiers .woff2 Inter (Google Fonts)
        //                cdn.jsdelivr.net    → fichiers .woff2 Bootstrap Icons
        //
        //  connect-src : api.iconify.design  → Iconify charge les SVG en JSON à la demande
        //                www.virustotal.com  → analyseur phishing
        //
        $csp = implode('; ', [
            "default-src 'self'",

            "script-src 'self' 'unsafe-inline' 'unsafe-eval'"
                . " https://cdn.jsdelivr.net"
                . " https://code.iconify.design",

            "style-src 'self' 'unsafe-inline'"
                . " https://cdn.jsdelivr.net"
                . " https://fonts.googleapis.com",

            "font-src 'self' data:"
                . " https://fonts.gstatic.com"
                . " https://cdn.jsdelivr.net",

            "img-src 'self' data:",

            "connect-src 'self'"
                . " https://api.iconify.design"
                . " https://www.virustotal.com",

            "frame-ancestors 'self'",
            "form-action 'self'",
            "base-uri 'self'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);

        // ── HSTS (HTTPS uniquement) ────────────────────────────────────────
        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=63072000; includeSubDomains; preload'
            );
        }
    }
}