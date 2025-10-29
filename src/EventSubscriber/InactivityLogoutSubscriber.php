<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

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

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 8],
        ];
    }

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


