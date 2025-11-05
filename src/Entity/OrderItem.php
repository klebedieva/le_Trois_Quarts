<?php

namespace App\Entity;
use App\Entity\Order;
use App\Entity\MenuItem;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Order line item.
 *
 * Denormalized fields (productId, productName, unitPrice, total) are kept intentionally
 * to preserve historical data even if related catalog items change or are removed.
 * Optional relation to MenuItem provides a live link when present.
 */
#[ORM\Entity]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $productId = null;

    #[ORM\Column(length: 255)]
    private ?string $productName = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $unitPrice = null;

    #[ORM\Column]
    private ?int $quantity = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $total = null;

    #[ORM\ManyToOne(inversedBy: 'items', cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'order_id', nullable: false, onDelete: 'CASCADE')]
    private ?Order $orderRef = null;

    #[ORM\ManyToOne(targetEntity: MenuItem::class)]
    #[ORM\JoinColumn(name: 'menu_item_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?MenuItem $menuItem = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProductId(): ?int
    {
        return $this->productId;
    }

    public function setProductId(int $productId): static
    {
        $this->productId = $productId;

        return $this;
    }

    public function getProductName(): ?string
    {
        return $this->productName;
    }

    public function setProductName(string $productName): static
    {
        $this->productName = $productName;

        return $this;
    }

    public function getUnitPrice(): ?string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(string $unitPrice): static
    {
        $this->unitPrice = $unitPrice;
        $this->recalculateTotal();

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;
        $this->recalculateTotal();

        return $this;
    }

    public function getTotal(): ?string
    {
        // If total is not set, calculate automatically
        if ($this->total === null && $this->unitPrice !== null && $this->quantity !== null) {
            $this->recalculateTotal();
        }
        
        return $this->total;
    }

    public function setTotal(string $total): static
    {
        $this->total = $total;

        return $this;
    }

    public function getOrderRef(): ?Order
    {
        return $this->orderRef;
    }

    public function setOrderRef(?Order $orderRef): static
    {
        $this->orderRef = $orderRef;

        return $this;
    }

    public function getMenuItem(): ?MenuItem
    {
        return $this->menuItem;
    }

    public function setMenuItem(?MenuItem $menuItem): static
    {
        $this->menuItem = $menuItem;
        if ($menuItem) {
            // Keep denormalized fields in sync for reporting and resilience
            $this->productId = $menuItem->getId();
            $this->productName = (string) $menuItem->getName();
            $this->unitPrice = (string) $menuItem->getPrice();
            $this->recalculateTotal();
        }
        return $this;
    }

    /**
     * Recalculates total cost based on quantity and unit price
     */
    public function recalculateTotal(): void
    {
        if ($this->unitPrice !== null && $this->quantity !== null) {
            $total = (float) $this->unitPrice * $this->quantity;
            $this->total = number_format($total, 2, '.', '');
        }
    }
}
