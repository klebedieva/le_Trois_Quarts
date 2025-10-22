<?php

namespace App\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class AdminVoter extends Voter
{
    protected function supports(string $attribute, $subject): bool
    {
        return $attribute === 'ROLE_ADMIN' || $attribute === 'ROLE_MODERATOR';
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user) {
            return false;
        }

        $roles = $user->getRoles();

        // Strict role checks:
        // - ROLE_ADMIN is granted only to admins
        // - ROLE_MODERATOR is granted to moderators and admins (admins inherit moderator permissions)
        if ($attribute === 'ROLE_ADMIN') {
            return in_array('ROLE_ADMIN', $roles, true);
        }

        if ($attribute === 'ROLE_MODERATOR') {
            return in_array('ROLE_MODERATOR', $roles, true) || in_array('ROLE_ADMIN', $roles, true);
        }

        return false;
    }
}



