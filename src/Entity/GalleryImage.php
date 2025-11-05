<?php

namespace App\Entity;

use App\Repository\GalleryImageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Public gallery image metadata.
 *
 * Used to display curated photos (categories: terrasse/interieur/plats/ambiance)
 * on the website. Only active images are exposed via the API.
 */
#[ORM\Entity(repositoryClass: GalleryImageRepository::class)]
#[ORM\Table(name: 'gallery_images')]
class GalleryImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre est requis')]
    #[Assert\Length(min: 2, max: 255, minMessage: 'Le titre doit contenir au moins 2 caractères')]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'La description est requise')]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom du fichier est requis', groups: ['create'])]
    private ?string $imagePath = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'La catégorie est requise')]
    #[Assert\Choice(
        choices: ['terrasse', 'interieur', 'plats', 'ambiance'],
        message: 'La catégorie doit être: terrasse, interieur, plats ou ambiance'
    )]
    private ?string $category = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotBlank(message: 'L\'ordre d\'affichage est requis')]
    private ?int $displayOrder = 0;

    #[ORM\Column(type: Types::BOOLEAN)]
    private ?bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->isActive = true;
        $this->displayOrder = 0;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(string $imagePath): static
    {
        $this->imagePath = $imagePath;

        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getDisplayOrder(): ?int
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder(int $displayOrder): static
    {
        $this->displayOrder = $displayOrder;

        return $this;
    }

    public function isIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

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

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Get the full URL path for the image
     */
    public function getFullImagePath(): string
    {
        return 'assets/img/' . $this->imagePath;
    }

    /**
     * Get category label in French
     */
    public function getCategoryLabel(): string
    {
        return match($this->category) {
            'terrasse' => 'Terrasse',
            'interieur' => 'Intérieur',
            'plats' => 'Plats',
            'ambiance' => 'Ambiance',
            default => 'Autre'
        };
    }
}

