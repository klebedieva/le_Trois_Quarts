<?php

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

class LoginAuditSubscriber implements EventSubscriberInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $securityAuditLogger)
    {
        $this->logger = $securityAuditLogger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
        ];
    }

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

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        $credentials = $event->getPassport()?->getBadge('user_badge');
        $email = method_exists($credentials, 'getUserIdentifier') ? $credentials->getUserIdentifier() : null;
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


