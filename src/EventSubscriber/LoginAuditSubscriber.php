<?php

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

/**
 * Login Audit Event Subscriber
 *
 * WHAT IT DOES:
 * This subscriber logs all login attempts (successful and failed) for security auditing.
 * It records:
 * - User email (if available)
 * - IP address
 * - User-Agent (browser/device info)
 * - Request path
 * - Error type (for failed logins)
 *
 * WHEN IT TRIGGERS:
 * - Automatically when a user successfully logs in (LoginSuccessEvent)
 * - Automatically when a login attempt fails (LoginFailureEvent)
 * - No manual call needed - Symfony Security calls it automatically
 *
 * WHY IT'S HIDDEN:
 * - Runs automatically, so you don't see it in login controllers
 * - But it's essential for security - helps track suspicious login attempts
 *
 * WHERE LOGS ARE STORED:
 * - Logs go to the security_audit logger channel
 * - Check config/packages/monolog.yaml for log file location
 *
 * HOW TO DEBUG:
 * - Check security audit logs for login events
 * - Look for "Login success" and "Login failure" messages
 */
class LoginAuditSubscriber implements EventSubscriberInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $securityAuditLogger)
    {
        $this->logger = $securityAuditLogger;
    }

    /**
     * Tell Symfony which events this subscriber listens to
     *
     * This method is called automatically by Symfony when the application starts.
     * It registers this class to listen to login events.
     *
     * @return array Event class => method to call
     */
    public static function getSubscribedEvents(): array
    {
        return [
            // When login succeeds, call onLoginSuccess()
            LoginSuccessEvent::class => 'onLoginSuccess',
            // When login fails, call onLoginFailure()
            LoginFailureEvent::class => 'onLoginFailure',
        ];
    }

    /**
     * Called automatically when a user successfully logs in
     *
     * This method logs successful login attempts for security auditing.
     *
     * @param LoginSuccessEvent $event Contains user and request information
     */
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $request = $event->getRequest();
        $user = $event->getUser();
        $ip = $request->getClientIp();
        $ua = (string)$request->headers->get('User-Agent', '');
        $this->logger->info('Login success', [
            'email' => method_exists($user, 'getEmail') ? $user->getEmail() : null,
            'ip' => $ip,
            'user_agent' => $ua,
            'path' => $request->getPathInfo(),
        ]);
    }

    /**
     * Called automatically when a login attempt fails
     *
     * This method logs failed login attempts for security auditing.
     * This helps identify brute-force attacks or suspicious activity.
     *
     * @param LoginFailureEvent $event Contains credentials and error information
     */
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();

        $email = null;
        $passport = $event->getPassport();
        if ($passport !== null) {
            $badge = $passport->getBadge('user_badge');
            if ($badge !== null && method_exists($badge, 'getUserIdentifier')) {
                $email = $badge->getUserIdentifier();
            }
        }
        // Fallback to submitted form data when passport/badge is unavailable (e.g. early failures)
        if ($email === null) {
            $email = $request->request->get('email');
        }
        $ip = $request->getClientIp();
        $ua = (string)$request->headers->get('User-Agent', '');
        $this->logger->warning('Login failure', [
            'email' => $email,
            'ip' => $ip,
            'user_agent' => $ua,
            'path' => $request->getPathInfo(),
            'error' => get_class($event->getException()),
        ]);
    }
}


