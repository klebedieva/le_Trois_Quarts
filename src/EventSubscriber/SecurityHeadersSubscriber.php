<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Security Headers Event Subscriber
 *
 * WHAT IT DOES:
 * This subscriber automatically adds security headers to all HTTP responses.
 * It runs AFTER the controller returns a response, but BEFORE the response is sent to the client.
 *
 * WHEN IT TRIGGERS:
 * - Automatically on EVERY HTTP response (KernelEvents::RESPONSE event)
 * - No manual call needed - Symfony calls it automatically
 *
 * WHAT HEADERS IT ADDS:
 * 1. CORS headers (for API endpoints only)
 *    - Allows cross-origin requests from allowed origins
 *    - Required for frontend JavaScript to call the API
 *
 * 2. Content Security Policy (CSP)
 *    - Prevents XSS attacks by controlling which resources can be loaded
 *    - Different rules for API vs regular pages
 *
 * 3. X-Frame-Options: DENY
 *    - Prevents clickjacking attacks (prevents site from being embedded in iframe)
 *
 * 4. X-Content-Type-Options: nosniff
 *    - Prevents MIME type sniffing attacks
 *
 * 5. Referrer-Policy
 *    - Controls how much referrer information is sent
 *
 * 6. Strict-Transport-Security (HSTS)
 *    - Forces HTTPS connections (only when using HTTPS)
 *
 * WHY IT'S HIDDEN:
 * - Runs automatically, so you don't see it in controllers
 * - But it's essential for security - all responses get these headers
 *
 * HOW TO DEBUG:
 * - Check response headers in browser DevTools (Network tab)
 * - Look for: Content-Security-Policy, X-Frame-Options, etc.
 */
class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    /**
     * Tell Symfony which events this subscriber listens to
     *
     * This method is called automatically by Symfony when the application starts.
     * It registers this class to listen to the RESPONSE event.
     *
     * @return array Event name => method to call
     */
    public static function getSubscribedEvents(): array
    {
        return [
            // When Symfony is about to send a response, call onKernelResponse()
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    /**
     * Called automatically when Symfony is about to send a response
     *
     * This method adds security headers to the response.
     * It runs for ALL responses (API and regular pages).
     *
     * @param ResponseEvent $event Contains the response that will be sent to client
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $request = $event->getRequest();
        
        // Check if this is an API endpoint (starts with /api)
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
                // Allow images from self, data URIs, localhost, jsDelivr (logos) and external image CDNs used in content (e.g., Pexels)
                $imgSrc = "'self' data: http://127.0.0.1:* https://127.0.0.1:* http://localhost:* https://localhost:* https://cdn.jsdelivr.net https://images.pexels.com";
            } else {
                $connectSrc = "'self'";
                // Allow images from self, data URIs, jsDelivr, and external image CDNs used on pages (e.g., Pexels)
                $imgSrc = "'self' data: https://cdn.jsdelivr.net https://images.pexels.com";
            }
            // Allow webfonts from jsDelivr (Bootstrap Icons) and Google Fonts, include data: for embedded fonts
            $fontSrc = "'self' data: https://fonts.gstatic.com https://cdn.jsdelivr.net";
            // Allow embedding Google Maps iframe on contact page; restrict to known hosts
            $frameSrc = "'self' https://www.google.com https://maps.google.com";
            // Expand script sources to allow data: URLs used by some loaders; add script-src-elem explicitly
            $scriptSrc = "'self' 'unsafe-inline' data: https://cdn.jsdelivr.net https://cdn.jsdelivr.net/npm";
            // On non-API pages, allow connecting to jsDelivr for source maps and assets
            if (!$isApi && $connectSrc === "'self'") {
                $connectSrc = "'self' https://cdn.jsdelivr.net";
            }
            $csp = "default-src 'self'; script-src $scriptSrc; script-src-elem $scriptSrc; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; img-src $imgSrc; font-src $fontSrc; connect-src $connectSrc; frame-src $frameSrc; frame-ancestors 'none'";
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


