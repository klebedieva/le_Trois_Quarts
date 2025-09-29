<?php

namespace App\Entity;

use App\Repository\MenuItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MenuItemRepository::class)]
#[ORM\HasLifecycleCallbacks]
class MenuItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $price = null;

    #[ORM\Column(length: 32)]
    private ?string $category = null;

    #[ORM\Column(length: 255)]
    private ?string $image = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $ingredients = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $preparation = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $prepTimeMin = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $prepTimeMax = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $chefTip = null;

    #[ORM\ManyToMany(targetEntity: Allergen::class, inversedBy: 'menuItems')]
    #[ORM\JoinTable(name: 'menu_item_allergen')]
    private Collection $allergens;

    #[ORM\Embedded(class: NutritionFacts::class)]
    private NutritionFacts $nutrition;

    /**
     * @var Collection<int, Badge>
     */
    #[ORM\ManyToMany(targetEntity: Badge::class, inversedBy: 'menuItems')]
    private Collection $badges;

    /**
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'menuItems')]
    #[ORM\JoinTable(name: 'menu_item_tag')]
    private Collection $tags;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->badges = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->allergens = new ArrayCollection();
        $this->nutrition = new NutritionFacts();
    }

    #[ORM\PrePersist]
    public function setTimestampsOnCreate(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
        if ($this->updatedAt === null) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    #[ORM\PreUpdate]
    public function touchUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;

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

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return Collection<int, Badge>
     */
    public function getBadges(): Collection
    {
        return $this->badges;
    }

    public function addBadge(Badge $badge): static
    {
        if (!$this->badges->contains($badge)) {
            $this->badges->add($badge);
        }

        return $this;
    }

    public function removeBadge(Badge $badge): static
    {
        $this->badges->removeElement($badge);

        return $this;
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
            // Synchroniser le côté inverse
            if (method_exists($tag, 'addMenuItem')) {
                $tag->addMenuItem($this);
            }
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    public function getIngredients(): ?string
    {
        return $this->ingredients;
    }

    public function setIngredients(?string $ingredients): static
    {
        // Если это не JSON, конвертируем в JSON массив
        if ($ingredients && !$this->isJson($ingredients)) {
            // Разбиваем по строкам и создаем JSON массив
            $lines = array_filter(array_map('trim', explode("\n", $ingredients)));
            if (!empty($lines)) {
                $ingredients = json_encode($lines, JSON_UNESCAPED_UNICODE);
            }
        }
        
        $this->ingredients = $ingredients;

        return $this;
    }

    private function isJson(string $string): bool
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    public function getIngredientsAsArray(): array
    {
        if (!$this->ingredients) {
            return [];
        }
        
        // Попробуем декодировать как JSON
        $decoded = json_decode($this->ingredients, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
        
        // Если не JSON, разбиваем по строкам
        return array_filter(array_map('trim', explode("\n", $this->ingredients)));
    }

    public function getPreparation(): ?string
    {
        return $this->preparation;
    }

    public function setPreparation(?string $preparation): static
    {
        $this->preparation = $preparation;

        return $this;
    }

    

    public function getPrepTimeMin(): ?int
    {
        return $this->prepTimeMin;
    }

    public function setPrepTimeMin(?int $prepTimeMin): static
    {
        $this->prepTimeMin = $prepTimeMin;

        return $this;
    }

    public function getPrepTimeMax(): ?int
    {
        return $this->prepTimeMax;
    }

    public function setPrepTimeMax(?int $prepTimeMax): static
    {
        $this->prepTimeMax = $prepTimeMax;

        return $this;
    }

    public function getChefTip(): ?string
    {
        return $this->chefTip;
    }

    public function setChefTip(?string $chefTip): static
    {
        $this->chefTip = $chefTip;

        return $this;
    }

    /**
     * @return Collection<int, Allergen>
     */
    public function getAllergens(): Collection
    {
        return $this->allergens;
    }

    public function addAllergen(Allergen $allergen): static
    {
        if (!$this->allergens->contains($allergen)) {
            $this->allergens->add($allergen);
        }

        return $this;
    }

    public function removeAllergen(Allergen $allergen): static
    {
        $this->allergens->removeElement($allergen);

        return $this;
    }

    public function getNutrition(): NutritionFacts
    {
        return $this->nutrition;
    }

    public function setNutrition(NutritionFacts $nutrition): static
    {
        $this->nutrition = $nutrition;

        return $this;
    }
}
