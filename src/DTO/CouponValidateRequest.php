<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Coupon Validation Request Data Transfer Object
 *
 * DTO for validating coupon validation requests from API clients.
 * Used with Symfony Validator to ensure data integrity.
 *
 * All validation messages are in French to match the application's language.
 */
class CouponValidateRequest
{
    #[Assert\NotBlank(message: 'Le code promo est requis')]
    #[Assert\Length(min: 3, max: 50, minMessage: 'Le code promo doit contenir au moins 3 caractères', maxMessage: 'Le code promo ne peut pas dépasser 50 caractères')]
    public ?string $code = null;

    #[Assert\NotBlank(message: 'Le montant de la commande est requis')]
    #[Assert\Type(type: 'numeric', message: 'Le montant de la commande doit être un nombre')]
    #[Assert\GreaterThan(value: 0, message: 'Le montant de la commande doit être supérieur à 0')]
    public ?float $orderAmount = null;
}

