<?php

namespace App\Enum;

enum UserRole: string
{
    case ADMIN = 'ROLE_ADMIN';
    case MODERATOR = 'ROLE_MODERATOR';
    case USER = 'ROLE_USER';

    public function getRoleName(): string
    {
        return $this->value;
    }

    public function getDisplayName(): string
    {
        return match($this) {
            self::ADMIN => 'Administrateur',
            self::MODERATOR => 'Modérateur',
            self::USER => 'Utilisateur',
        };
    }
}
