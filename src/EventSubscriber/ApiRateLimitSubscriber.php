<?php

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class ApiRateLimitSubscriber implements EventSubscriberInterface
{
    private RateLimiterFactory $publicLimiter;
    private RateLimiterFactory $sensitiveLimiter;
    private int $maxJsonBytes;

    public function __construct(
        #[Autowire(service: 'limiter.api_public')] RateLimiterFactory $publicLimiter,
        #[Autowire(service: 'limiter.api_sensitive')] RateLimiterFactory $sensitiveLimiter,
        int $maxJsonBytes = 1048576
    ) {
        $this->publicLimiter = $publicLimiter;
        $this->sensitiveLimiter = $sensitiveLimiter;
        $this->maxJsonBytes = $maxJsonBytes; // 1 MB default
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 9],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Handle OPTIONS preflight requests for CORS
        if ($request->getMethod() === 'OPTIONS' && strpos($path, '/api') === 0) {
            $response = new JsonResponse(null, 204);
            $origin = $request->headers->get('Origin');
            // Allow the origin from request, or fallback to same origin
            $allowedOrigin = $origin ?: $request->getSchemeAndHttpHost();
            $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-CSRF-Token');
            $response->headers->set('Access-Control-Max-Age', '3600');
            $event->setResponse($response);
            return;
        }

        if (strpos($path, '/api') !== 0) {
            return;
        }

        // Enforce body size limit for JSON requests
        if (in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'], true)) {
            $contentType = (string) $request->headers->get('Content-Type', '');
            $length = (int) ($request->headers->get('Content-Length') ?? 0);
            if (str_contains($contentType, 'application/json') && $length > $this->maxJsonBytes) {
                $event->setResponse(new JsonResponse([
                    'success' => false,
                    'message' => 'Payload too large',
                ], 413));
                return;
            }
        }

        // Rate limit: stricter for state-changing methods
        $limiter = in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            ? $this->sensitiveLimiter
            : $this->publicLimiter;

        $ip = (string) $request->getClientIp();
        $limit = $limiter->create($ip);
        $token = $limit->consume(1);

        if (!$token->isAccepted()) {
            $retryAfter = $token->getRetryAfter();
            $headers = [];
            if ($retryAfter) {
                $headers['Retry-After'] = max(1, $retryAfter->getTimestamp() - time());
            }

            $event->setResponse(new JsonResponse([
                'success' => false,
                'message' => 'Too many requests',
            ], 429, $headers));
        }
    }
}


