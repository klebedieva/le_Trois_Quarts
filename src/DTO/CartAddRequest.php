<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Cart Add Request Data Transfer Object
 *
 * DTO for validating cart item addition requests from API clients.
 * Used with Symfony Validator to ensure data integrity.
 *
 * All validation messages are in French to match the application's language.
 */
class CartAddRequest
{
    #[Assert\NotBlank(message: 'L\'ID de l\'article est requis')]
    #[Assert\Type(type: 'integer', message: 'L\'ID de l\'article doit être un entier')]
    #[Assert\Positive(message: 'L\'ID de l\'article doit être positif')]
    public ?int $itemId = null;

    #[Assert\Type(type: 'integer', message: 'La quantité doit être un entier')]
    #[Assert\Positive(message: 'La quantité doit être positive')]
    #[Assert\LessThanOrEqual(value: 100, message: 'La quantité ne peut pas dépasser 100')]
    public ?int $quantity = 1;
}

