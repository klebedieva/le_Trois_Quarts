<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Address Validation Request Data Transfer Object
 *
 * DTO for validating address validation requests from API clients.
 * Used with Symfony Validator to ensure data integrity.
 *
 * All validation messages are in French to match the application's language.
 */
class AddressValidationRequest
{
    #[Assert\NotBlank(message: 'Le code postal est requis')]
    #[Assert\Length(min: 5, max: 10, minMessage: 'Le code postal doit contenir au moins 5 caractères', maxMessage: 'Le code postal ne peut pas dépasser 10 caractères')]
    public ?string $zipCode = null;
}

