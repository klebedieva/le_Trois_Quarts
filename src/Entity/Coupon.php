<?php

namespace App\Entity;

use App\Repository\CouponRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Coupon code for applying discounts to orders.
 *
 * Supports percentage and fixed-amount discounts, optional min order amount,
 * maximum discount, validity window, active flag, and usage limit.
 * Usage count is tracked to enforce limits.
 */
#[ORM\Entity(repositoryClass: CouponRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'coupon')]
#[ORM\UniqueConstraint(name: 'UNIQ_64BF3F0277153098', columns: ['code'])]
class Coupon
{
    // Discount type constants
    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_FIXED = 'fixed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $code = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 20)]
    private ?string $discountType = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $discountValue = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $minOrderAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $maxDiscount = null;

    #[ORM\Column(nullable: true)]
    private ?int $usageLimit = null;

    #[ORM\Column(options: ['default' => 0])]
    private ?int $usageCount = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $validFrom = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $validUntil = null;

    #[ORM\Column(options: ['default' => true])]
    private ?bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'coupon')]
    private Collection $orders;

    public function __construct()
    {
        $this->orders = new ArrayCollection();
        $this->usageCount = 0;
        $this->isActive = true;
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

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

    public function getDiscountType(): ?string
    {
        return $this->discountType;
    }

    public function setDiscountType(string $discountType): static
    {
        $this->discountType = $discountType;

        return $this;
    }

    public function getMinOrderAmount(): ?string
    {
        return $this->minOrderAmount;
    }

    public function setMinOrderAmount(?string $minOrderAmount): static
    {
        $this->minOrderAmount = $minOrderAmount;

        return $this;
    }

    public function getMaxDiscount(): ?string
    {
        return $this->maxDiscount;
    }

    public function setMaxDiscount(?string $maxDiscount): static
    {
        $this->maxDiscount = $maxDiscount;

        return $this;
    }

    public function getUsageLimit(): ?int
    {
        return $this->usageLimit;
    }

    public function setUsageLimit(?int $usageLimit): static
    {
        $this->usageLimit = $usageLimit;

        return $this;
    }

    public function getUsageCount(): ?int
    {
        return $this->usageCount;
    }

    public function setUsageCount(int $usageCount): static
    {
        $this->usageCount = $usageCount;

        return $this;
    }

    public function getValidFrom(): ?\DateTime
    {
        return $this->validFrom;
    }

    public function setValidFrom(?\DateTime $validFrom): static
    {
        $this->validFrom = $validFrom;

        return $this;
    }

    public function getValidUntil(): ?\DateTime
    {
        return $this->validUntil;
    }

    public function setValidUntil(?\DateTime $validUntil): static
    {
        $this->validUntil = $validUntil;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getDiscountValue(): ?string
    {
        return $this->discountValue;
    }

    public function setDiscountValue(string $discountValue): static
    {
        $this->discountValue = $discountValue;

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

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return Collection<int, Order>
     */
    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function addOrder(Order $order): static
    {
        if (!$this->orders->contains($order)) {
            $this->orders->add($order);
            $order->setCoupon($this);
        }

        return $this;
    }

    public function removeOrder(Order $order): static
    {
        if ($this->orders->removeElement($order)) {
            // set the owning side to null (unless already changed)
            if ($order->getCoupon() === $this) {
                $order->setCoupon(null);
            }
        }

        return $this;
    }

    /**
     * Check if the coupon is currently valid
     */
    public function isValid(): bool
    {
        if (!$this->isActive) {
            return false;
        }

        $now = new \DateTime();

        if ($this->validFrom && $now < $this->validFrom) {
            return false;
        }

        if ($this->validUntil && $now > $this->validUntil) {
            return false;
        }

        return true;
    }

    /**
     * Check if the coupon can be used (valid and not exceeded usage limit)
     */
    public function canBeUsed(): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        if ($this->usageLimit !== null && $this->usageCount >= $this->usageLimit) {
            return false;
        }

        return true;
    }

    /**
     * Check if the coupon can be applied to an order of given amount
     */
    public function canBeAppliedToAmount(float $amount): bool
    {
        if (!$this->canBeUsed()) {
            return false;
        }

        if ($this->minOrderAmount !== null && $amount < (float) $this->minOrderAmount) {
            return false;
        }

        return true;
    }

    /**
     * Calculate discount amount for given order total
     */
    public function calculateDiscount(float $orderAmount): float
    {
        if (!$this->canBeAppliedToAmount($orderAmount)) {
            return 0.0;
        }

        $discount = 0.0;

        if ($this->discountType === self::TYPE_PERCENTAGE) {
            $discount = $orderAmount * ((float) $this->discountValue / 100);
        } elseif ($this->discountType === self::TYPE_FIXED) {
            $discount = (float) $this->discountValue;
        }

        // Apply max discount limit if set
        if ($this->maxDiscount !== null) {
            $discount = min($discount, (float) $this->maxDiscount);
        }

        // Discount cannot exceed order amount
        $discount = min($discount, $orderAmount);

        return round($discount, 2);
    }

    /**
     * Increment usage count
     */
    public function incrementUsage(): static
    {
        $this->usageCount++;

        return $this;
    }

    /**
     * Get available discount types
     */
    public static function getDiscountTypes(): array
    {
        return [
            self::TYPE_PERCENTAGE => 'Percentage',
            self::TYPE_FIXED => 'Fixed Amount',
        ];
    }

    public function __toString(): string
    {
        return $this->code ?? '';
    }
}
