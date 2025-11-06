<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Inactivity Logout Event Subscriber
 *
 * WHAT IT DOES:
 * This subscriber automatically logs out users who have been inactive for too long.
 * It tracks the last activity time and logs out users after a period of inactivity.
 *
 * WHEN IT TRIGGERS:
 * - Automatically on EVERY request to /admin/* pages (KernelEvents::REQUEST event)
 * - Runs BEFORE the controller is called
 * - Priority 8: runs early in the request lifecycle
 *
 * HOW IT WORKS:
 * 1. Checks if request is to /admin/* area
 * 2. Gets last activity time from session
 * 3. If last activity was more than idleTimeoutSeconds ago:
 *    - Invalidates session
 *    - Clears authentication token
 *    - Redirects to login page
 * 4. Otherwise, updates last activity time to current time
 *
 * DEFAULT TIMEOUT:
 * - 1800 seconds (30 minutes) of inactivity
 * - Configurable via constructor parameter
 *
 * WHY IT'S HIDDEN:
 * - Runs automatically, so you don't see it in admin controllers
 * - But it's essential for security - prevents unauthorized access to admin panel
 *
 * HOW TO DEBUG:
 * - If you're logged out unexpectedly, check if you were inactive for 30+ minutes
 * - Check session data: $session->get('last_activity_ts')
 */
class InactivityLogoutSubscriber implements EventSubscriberInterface
{
    private int $idleTimeoutSeconds;
    private UrlGeneratorInterface $urlGenerator;
    private TokenStorageInterface $tokenStorage;

    public function __construct(UrlGeneratorInterface $urlGenerator, TokenStorageInterface $tokenStorage, int $idleTimeoutSeconds = 1800)
    {
        $this->urlGenerator = $urlGenerator;
        $this->tokenStorage = $tokenStorage;
        $this->idleTimeoutSeconds = $idleTimeoutSeconds;
    }

    /**
     * Tell Symfony which events this subscriber listens to
     *
     * Priority 8 means this runs early in the request lifecycle,
     * before most other subscribers.
     *
     * @return array Event name => [method to call, priority]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            // When Symfony receives a request, call onKernelRequest()
            // Priority 8: runs early, before rate limiting and field validation
            KernelEvents::REQUEST => ['onKernelRequest', 8],
        ];
    }

    /**
     * Called automatically when Symfony receives a request
     *
     * This method checks for user inactivity and logs out if timeout exceeded.
     * Only applies to /admin/* pages.
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

        // Only enforce on admin area
        if (strpos($path, '/admin') !== 0) {
            return;
        }

        $session = $request->getSession();
        if (!$session) {
            return;
        }

        $now = time();
        $lastActivity = (int)($session->get('last_activity_ts', 0));

        if ($lastActivity > 0 && ($now - $lastActivity) > $this->idleTimeoutSeconds) {
            $session->invalidate();
            $this->tokenStorage->setToken(null);
            $loginUrl = $this->urlGenerator->generate('app_login');
            $event->setResponse(new RedirectResponse($loginUrl));
            return;
        }

        $session->set('last_activity_ts', $now);
    }
}


