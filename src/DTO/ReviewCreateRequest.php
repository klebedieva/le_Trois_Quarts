<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

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


