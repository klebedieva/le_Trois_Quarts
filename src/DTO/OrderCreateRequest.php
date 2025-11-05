<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Order Creation Request Data Transfer Object
 *
 * DTO for validating order creation data from API clients.
 * Used with Symfony Validator to ensure data integrity before creating orders.
 *
 * All validation messages are in French to match the application's language.
 */
class OrderCreateRequest
{
    #[Assert\Choice(choices: ['delivery', 'pickup'], message: 'Le mode de livraison doit être "delivery" ou "pickup"')]
    public ?string $deliveryMode = null;

    #[Assert\Length(max: 255, maxMessage: 'L\'adresse de livraison ne peut pas dépasser 255 caractères')]
    public ?string $deliveryAddress = null;

    #[Assert\Length(max: 10, maxMessage: 'Le code postal ne peut pas dépasser 10 caractères')]
    public ?string $deliveryZip = null;

    #[Assert\Length(max: 500, maxMessage: 'Les instructions de livraison ne peuvent pas dépasser 500 caractères')]
    public ?string $deliveryInstructions = null;

    #[Assert\Type(type: 'numeric', message: 'Les frais de livraison doivent être un nombre')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Les frais de livraison ne peuvent pas être négatifs')]
    public ?float $deliveryFee = null;

    #[Assert\Choice(choices: ['card', 'cash', 'tickets'], message: 'Le mode de paiement doit être "card", "cash" ou "tickets"')]
    public ?string $paymentMode = null;

    #[Assert\NotBlank(message: 'Le prénom est requis')]
    #[Assert\Length(min: 2, max: 100, minMessage: 'Le prénom doit contenir au moins 2 caractères', maxMessage: 'Le prénom ne peut pas dépasser 100 caractères')]
    public ?string $clientFirstName = null;

    #[Assert\NotBlank(message: 'Le nom est requis')]
    #[Assert\Length(min: 2, max: 100, minMessage: 'Le nom doit contenir au moins 2 caractères', maxMessage: 'Le nom ne peut pas dépasser 100 caractères')]
    public ?string $clientLastName = null;

    #[Assert\NotBlank(message: 'Le numéro de téléphone est requis')]
    #[Assert\Length(min: 10, max: 20, minMessage: 'Le numéro de téléphone doit contenir au moins 10 caractères', maxMessage: 'Le numéro de téléphone ne peut pas dépasser 20 caractères')]
    public ?string $clientPhone = null;

    #[Assert\Email(message: 'L\'email n\'est pas valide')]
    #[Assert\Length(max: 255, maxMessage: 'L\'email ne peut pas dépasser 255 caractères')]
    public ?string $clientEmail = null;

    #[Assert\Type(type: 'integer', message: 'L\'ID du coupon doit être un entier')]
    #[Assert\Positive(message: 'L\'ID du coupon doit être positif')]
    public ?int $couponId = null;

    #[Assert\Type(type: 'numeric', message: 'Le montant de la réduction doit être un nombre')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le montant de la réduction ne peut pas être négatif')]
    public ?float $discountAmount = null;
}

