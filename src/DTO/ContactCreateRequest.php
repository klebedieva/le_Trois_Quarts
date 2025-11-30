<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Contact Form Submission Request Data Transfer Object
 *
 * DTO for validating contact form submissions from API clients.
 * Used with Symfony Validator to ensure data integrity before creating contact messages.
 *
 * All validation messages are in French to match the application's language.
 */
class ContactCreateRequest
{
    #[Assert\NotBlank(message: 'Le prénom est requis')]
    #[Assert\Length(min: 2, max: 100, minMessage: 'Le prénom doit contenir au moins 2 caractères', 
    maxMessage: 'Le prénom ne peut pas dépasser 100 caractères')]
    public ?string $firstName = null;

    #[Assert\NotBlank(message: 'Le nom est requis')]
    #[Assert\Length(min: 2, max: 100, minMessage: 'Le nom doit contenir au moins 2 caractères', 
    maxMessage: 'Le nom ne peut pas dépasser 100 caractères')]
    public ?string $lastName = null;

    #[Assert\NotBlank(message: 'L\'email est requis')]
    #[Assert\Email(message: 'L\'email doit être valide')]
    #[Assert\Length(max: 255, maxMessage: 'L\'email ne peut pas dépasser 255 caractères')]
    public ?string $email = null;

    #[Assert\Length(min: 10, max: 20, minMessage: 'Le numéro de téléphone doit contenir au moins 10 caractères', 
    maxMessage: 'Le numéro de téléphone ne peut pas dépasser 20 caractères')]
    public ?string $phone = null;

    #[Assert\NotBlank(message: 'Le sujet est requis')]
    #[Assert\Length(max: 200, maxMessage: 'Le sujet ne peut pas dépasser 200 caractères')]
    public ?string $subject = null;

    #[Assert\NotBlank(message: 'Le message est requis')]
    #[Assert\Length(min: 10, max: 2000, minMessage: 'Le message doit contenir au moins 10 caractères', maxMessage: 'Le message ne peut pas dépasser 2000 caractères')]
    public ?string $message = null;

    #[Assert\IsTrue(message: 'Vous devez accepter d\'être contacté')]
    public ?bool $consent = false;
}

