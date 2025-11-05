<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Review Creation Request Data Transfer Object
 *
 * DTO for validating and accepting review submission data from API clients.
 * Used with Symfony Validator to ensure data integrity before creating review entities.
 *
 * All validation messages are in French to match the application's language.
 *
 * Validation rules:
 * - name: Required, minimum 2 characters
 * - email: Optional, must be valid email format if provided
 * - rating: Required, must be between 1 and 5 (inclusive)
 * - comment: Required, minimum 10 characters
 */
class ReviewCreateRequest
{
    #[Assert\NotBlank(message: 'Le nom est requis')]
    #[Assert\Length(min: 2, minMessage: 'Le nom doit contenir au moins 2 caractères')]
    public ?string $name = null;

    #[Assert\Email(message: 'Email invalide')]
    public ?string $email = null;

    #[Assert\NotNull(message: 'La note est requise')]
    #[Assert\Range(min: 1, max: 5, notInRangeMessage: 'La note doit être entre 1 et 5')]
    public ?int $rating = null;

    #[Assert\NotBlank(message: 'Le commentaire est requis')]
    #[Assert\Length(min: 10, minMessage: 'Le commentaire doit contenir au moins 10 caractères')]
    public ?string $comment = null;
}


