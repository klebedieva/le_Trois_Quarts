<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Full Address Validation Request Data Transfer Object
 *
 * DTO for validating full address validation requests from API clients.
 * Used with Symfony Validator to ensure data integrity.
 *
 * All validation messages are in French to match the application's language.
 */
class AddressFullValidationRequest
{
    #[Assert\NotBlank(message: 'L\'adresse est requise')]
    #[Assert\Length(min: 5, max: 255, minMessage: 'L\'adresse doit contenir au moins 5 caractères', maxMessage: 'L\'adresse ne peut pas dépasser 255 caractères')]
    public ?string $address = null;

    #[Assert\Length(max: 10, maxMessage: 'Le code postal ne peut pas dépasser 10 caractères')]
    public ?string $zipCode = null;
}

