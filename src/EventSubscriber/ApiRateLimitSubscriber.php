<?php

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * API Rate Limit Event Subscriber
 *
 * WHAT IT DOES:
 * This subscriber limits how many requests a client can make to the API.
 * It prevents abuse and DoS attacks by:
 * 1. Limiting request rate per IP address
 * 2. Different limits for read operations (GET) vs write operations (POST/PUT/PATCH/DELETE)
 * 3. Handling CORS preflight requests (OPTIONS)
 * 4. Enforcing JSON payload size limits
 *
 * WHEN IT TRIGGERS:
 * - Automatically on EVERY API request (KernelEvents::REQUEST event)
 * - Runs BEFORE the controller is called
 * - Only processes requests to /api/* endpoints
 * - Priority 9: runs BEFORE JsonFieldWhitelistSubscriber (priority 10)
 *
 * HOW IT WORKS:
 * 1. Handles OPTIONS preflight requests for CORS (returns 204 immediately)
 * 2. Checks JSON payload size (rejects if too large)
 * 3. Applies rate limiting based on request method:
 *    - Sensitive limiter: for POST/PUT/PATCH/DELETE (stricter limits)
 *    - Public limiter: for GET requests (more lenient limits)
 * 4. If limit exceeded, returns 429 (Too Many Requests) error
 *
 * WHY IT'S HIDDEN:
 * - Runs automatically, so controllers don't need to check rate limits manually
 * - But it's essential for security - prevents API abuse
 *
 * HOW TO DEBUG:
 * - If rate limit exceeded, you'll get a 429 error
 * - Check Retry-After header to see when you can retry
 * - Rate limiters are configured in config/packages/rate_limiter.yaml
 */
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

    /**
     * Tell Symfony which events this subscriber listens to
     *
     * Priority 9 means this runs BEFORE JsonFieldWhitelistSubscriber (priority 10),
     * so payload size is checked first, then field validation happens.
     *
     * @return array Event name => [method to call, priority]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            // When Symfony receives a request, call onKernelRequest()
            // Priority 9: runs before field validation (priority 10)
            KernelEvents::REQUEST => ['onKernelRequest', 9],
        ];
    }

    /**
     * Called automatically when Symfony receives a request
     *
     * This method applies rate limiting and payload size checks.
     * If limits are exceeded, it returns an error response immediately.
     *
     * @param RequestEvent $event Contains the request that will be processed
     */
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
            if (str_contains($contentType, 'application/json')) {
                // First check: Content-Length header (fast early rejection if header is too large)
                // Note: This is not secure by itself as header can be faked, but useful for early rejection
                $contentLength = (int) ($request->headers->get('Content-Length') ?? 0);
                if ($contentLength > $this->maxJsonBytes) {
                    $event->setResponse(new JsonResponse([
                        'success' => false,
                        'message' => 'Payload too large',
                    ], 413));
                    return;
                }
                
                // Second check: Actual body size (reliable validation)
                // This protects against clients that fake the Content-Length header
                
                $rawContent = $request->getContent(false);
                if ($rawContent !== false && strlen($rawContent) > $this->maxJsonBytes) {
                    $event->setResponse(new JsonResponse([
                        'success' => false,
                        'message' => 'Payload too large',
                    ], 413));
                    return;
                }
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


