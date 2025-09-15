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
        
        // Allow access if user has ROLE_ADMIN or ROLE_MODERATOR
        return in_array('ROLE_ADMIN', $roles) || in_array('ROLE_MODERATOR', $roles);
    }
}



