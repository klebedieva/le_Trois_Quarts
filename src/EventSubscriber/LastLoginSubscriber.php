<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Last Login Event Subscriber
 *
 * WHAT IT DOES:
 * This subscriber automatically updates the user's last login timestamp
 * when they successfully log in.
 *
 * WHEN IT TRIGGERS:
 * - Automatically when a user successfully logs in (LoginSuccessEvent)
 * - No manual call needed - Symfony Security calls it automatically
 * - Runs AFTER login is successful, but BEFORE the response is sent
 *
 * HOW IT WORKS:
 * 1. Listens to LoginSuccessEvent
 * 2. Gets the logged-in user from the event
 * 3. Checks if user is an instance of User entity
 * 4. Updates user's lastLoginAt field to current timestamp
 * 5. Saves to database (flush)
 *
 * WHY IT'S HIDDEN:
 * - Runs automatically, so you don't need to update lastLoginAt in login controllers
 * - But it's useful for tracking user activity
 *
 * HOW TO DEBUG:
 * - Check User entity's lastLoginAt field after login
 * - Should be updated to current timestamp
 */
class LastLoginSubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Tell Symfony which events this subscriber listens to
     *
     * This method is called automatically by Symfony when the application starts.
     * It registers this class to listen to login success events.
     *
     * @return array Event class => method to call
     */
    public static function getSubscribedEvents(): array
    {
        return [
            // When login succeeds, call onLoginSuccess()
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    /**
     * Called automatically when a user successfully logs in
     *
     * This method updates the user's last login timestamp in the database.
     *
     * @param LoginSuccessEvent $event Contains user information
     */
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $user->setLastLoginAt(new \DateTimeImmutable());

        // Flush only the change for this managed entity
        $this->entityManager->flush();
    }
}


