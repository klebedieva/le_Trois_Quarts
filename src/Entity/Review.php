<?php

namespace App\Entity;

use App\Repository\ReviewRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\MenuItem;

#[ORM\Entity(repositoryClass: ReviewRepository::class)]
#[ORM\Table(name: 'reviews')]
class Review
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom est requis')]
    #[Assert\Length(min: 2, max: 255, minMessage: 'Le nom doit contenir au moins 2 caractères')]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Email(message: 'L\'email n\'est pas valide')]
    private ?string $email = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotBlank(message: 'La note est requise')]
    #[Assert\Range(min: 1, max: 5, notInRangeMessage: 'La note doit être entre 1 et 5')]
    private ?int $rating = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Le commentaire est requis')]
    #[Assert\Length(min: 10, minMessage: 'Le commentaire doit contenir au moins 10 caractères')]
    private ?string $comment = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private ?bool $isApproved = false;

    // Link a review to a specific dish. When null, the review is about the restaurant in general.
    #[ORM\ManyToOne(targetEntity: MenuItem::class)]
    #[ORM\JoinColumn(name: 'menu_item_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?MenuItem $menuItem = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(int $rating): static
    {
        $this->rating = $rating;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function isIsApproved(): ?bool
    {
        return $this->isApproved;
    }

    public function setIsApproved(bool $isApproved): static
    {
        $this->isApproved = $isApproved;

        return $this;
    }

    /**
     * Get the dish this review belongs to. Null means global restaurant review.
     */
    public function getMenuItem(): ?MenuItem
    {
        return $this->menuItem;
    }

    /**
     * Associate this review with a dish (MenuItem).
     */
    public function setMenuItem(?MenuItem $menuItem): static
    {
        $this->menuItem = $menuItem;
        return $this;
    }
}