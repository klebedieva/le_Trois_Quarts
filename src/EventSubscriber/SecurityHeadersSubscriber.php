<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $request = $event->getRequest();
        $isApi = strpos($request->getPathInfo(), '/api') === 0;

        // CORS headers for API endpoints
        if ($isApi && !$response->headers->has('Access-Control-Allow-Origin')) {
            $origin = $request->headers->get('Origin');
            // Allow same origin requests (both HTTP and HTTPS for localhost)
            if ($origin) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
            } else {
                // Fallback to same origin
                $response->headers->set('Access-Control-Allow-Origin', $request->getSchemeAndHttpHost());
            }
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-CSRF-Token');
            $response->headers->set('Access-Control-Allow-Credentials', 'false');
            $response->headers->set('Access-Control-Max-Age', '3600');
        }

        // Content Security Policy: adjust as needed for allowed sources
        if (!$response->headers->has('Content-Security-Policy')) {
            // For API pages, allow connections and images from same origin (HTTP and HTTPS for localhost)
            if ($isApi) {
                $connectSrc = "'self' http://127.0.0.1:* https://127.0.0.1:* http://localhost:* https://localhost:*";
                // Allow images from self, data URIs, localhost, and CDN (for NelmioApiDocBundle logo)
                $imgSrc = "'self' data: http://127.0.0.1:* https://127.0.0.1:* http://localhost:* https://localhost:* https://cdn.jsdelivr.net";
            } else {
                $connectSrc = "'self'";
                // Allow images from self, data URIs, and CDN (for any CDN-hosted images)
                $imgSrc = "'self' data: https://cdn.jsdelivr.net";
            }
            // Allow webfonts from jsDelivr (Bootstrap Icons) and Google Fonts, include data: for embedded fonts
            $fontSrc = "'self' data: https://fonts.gstatic.com https://cdn.jsdelivr.net";
            // Allow embedding Google Maps iframe on contact page; restrict to known hosts
            $frameSrc = "'self' https://www.google.com https://maps.google.com";
            $csp = "default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.jsdelivr.net/npm; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; img-src $imgSrc; font-src $fontSrc; connect-src $connectSrc; frame-src $frameSrc; frame-ancestors 'none'";
            $response->headers->set('Content-Security-Policy', $csp);
        }

        // Clickjacking protection
        if (!$response->headers->has('X-Frame-Options')) {
            $response->headers->set('X-Frame-Options', 'DENY');
        }

        // MIME sniffing protection
        if (!$response->headers->has('X-Content-Type-Options')) {
            $response->headers->set('X-Content-Type-Options', 'nosniff');
        }

        // Referrer policy
        if (!$response->headers->has('Referrer-Policy')) {
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }

        // HSTS only when HTTPS
        if ($request->isSecure() && !$response->headers->has('Strict-Transport-Security')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }
    }
}


