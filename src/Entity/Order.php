<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use App\Enum\OrderStatus;
use App\Enum\DeliveryMode;
use App\Enum\PaymentMode;
use Doctrine\ORM\Mapping as ORM;

/**
 * Order aggregate root.
 *
 * - Monetary fields stored as DECIMAL/string to avoid floating precision issues;
 *   formatting is handled in services/UI.
 * - Status/DeliveryMode/PaymentMode are PHP enums; DB stores string values.
 * - Relation to Coupon is nullable (SET NULL) to preserve historical orders when coupons are deleted.
 */
#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', enumType: OrderStatus::class, options: ['default' => 'pending'])]
    private OrderStatus $status = OrderStatus::PENDING;

    #[ORM\Column(type: 'string', enumType: DeliveryMode::class, options: ['default' => 'delivery'])]
    private DeliveryMode $deliveryMode = DeliveryMode::DELIVERY;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $deliveryAddress = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $deliveryZip = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $deliveryInstructions = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $deliveryFee = null;

    #[ORM\Column(type: 'string', enumType: PaymentMode::class, options: ['default' => 'card'])]
    private PaymentMode $paymentMode = PaymentMode::CARD;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $subtotal = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $taxAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $total = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 255)]
    private ?string $no = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $clientEmail = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $clientName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $clientFirstName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $clientLastName = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $clientPhone = null;

    #[ORM\ManyToOne(targetEntity: Coupon::class, inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Coupon $coupon = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => '0.00'])]
    private ?string $discountAmount = '0.00';

    /**
     * @var Collection<int, OrderItem>
     */
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'orderRef', orphanRemoval: true, cascade: ['persist'])]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatus(): OrderStatus
    {
    return $this->status;
    }

    public function setStatus(OrderStatus $status): self
    {
    $this->status = $status;
    return $this;
    }

    public function getDeliveryMode(): DeliveryMode
    {
    return $this->deliveryMode;
    }

    public function setDeliveryMode(DeliveryMode $deliveryMode): self
    {
    $this->deliveryMode = $deliveryMode;
    return $this;
    }

    public function getDeliveryAddress(): ?string
    {
        return $this->deliveryAddress;
    }

    public function setDeliveryAddress(?string $deliveryAddress): static
    {
        $this->deliveryAddress = $deliveryAddress;

        return $this;
    }

    public function getDeliveryZip(): ?string
    {
        return $this->deliveryZip;
    }

    public function setDeliveryZip(?string $deliveryZip): static
    {
        $this->deliveryZip = $deliveryZip;

        return $this;
    }

    public function getDeliveryInstructions(): ?string
    {
        return $this->deliveryInstructions;
    }

    public function setDeliveryInstructions(?string $deliveryInstructions): static
    {
        $this->deliveryInstructions = $deliveryInstructions;

        return $this;
    }

    public function getDeliveryFee(): ?string
    {
        return $this->deliveryFee;
    }

    public function setDeliveryFee(string $deliveryFee): static
    {
        $this->deliveryFee = $deliveryFee;

        return $this;
    }

    public function getPaymentMode(): PaymentMode
    {
    return $this->paymentMode;
    }

    public function setPaymentMode(PaymentMode $paymentMode): self
    {
    $this->paymentMode = $paymentMode;
    return $this;
    }

    public function getSubtotal(): ?string
    {
        return $this->subtotal;
    }

    public function setSubtotal(string $subtotal): static
    {
        $this->subtotal = $subtotal;

        return $this;
    }

    public function getTaxAmount(): ?string
    {
        return $this->taxAmount;
    }

    public function setTaxAmount(string $taxAmount): static
    {
        $this->taxAmount = $taxAmount;

        return $this;
    }

    public function getTotal(): ?string
    {
        return $this->total;
    }

    public function setTotal(string $total): static
    {
        $this->total = $total;

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

    public function getNo(): ?string
    {
        return $this->no;
    }

    public function setNo(string $no): static
    {
        $this->no = $no;

        return $this;
    }

    public function getClientEmail(): ?string
    {
        return $this->clientEmail;
    }

    public function setClientEmail(?string $clientEmail): static
    {
        $this->clientEmail = $clientEmail;

        return $this;
    }

    public function getClientName(): ?string
    {
        return $this->clientName;
    }

    public function setClientName(?string $clientName): static
    {
        $this->clientName = $clientName;

        return $this;
    }

    public function getClientFirstName(): ?string
    {
        return $this->clientFirstName;
    }

    public function setClientFirstName(?string $clientFirstName): static
    {
        $this->clientFirstName = $clientFirstName;

        return $this;
    }

    public function getClientLastName(): ?string
    {
        return $this->clientLastName;
    }

    public function setClientLastName(?string $clientLastName): static
    {
        $this->clientLastName = $clientLastName;

        return $this;
    }

    public function getClientPhone(): ?string
    {
        return $this->clientPhone;
    }

    public function setClientPhone(?string $clientPhone): static
    {
        $this->clientPhone = $clientPhone;

        return $this;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(OrderItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setOrderRef($this);
        }

        return $this;
    }

    public function removeItem(OrderItem $item): static
    {
        if ($this->items->removeElement($item)) {
            // set the owning side to null (unless already changed)
            if ($item->getOrderRef() === $this) {
                $item->setOrderRef(null);
            }
        }

        return $this;
    }

    public function getCoupon(): ?Coupon
    {
        return $this->coupon;
    }

    public function setCoupon(?Coupon $coupon): static
    {
        $this->coupon = $coupon;

        return $this;
    }

    public function getDiscountAmount(): ?string
    {
        return $this->discountAmount;
    }

    public function setDiscountAmount(string $discountAmount): static
    {
        $this->discountAmount = $discountAmount;

        return $this;
    }

    /**
     * Apply a coupon to the order
     */
    public function applyCoupon(Coupon $coupon): bool
    {
        $subtotalWithTax = 0;
        
        foreach ($this->items as $item) {
            $subtotalWithTax += (float) $item->getTotal();
        }

        $deliveryFee = (float) ($this->deliveryFee ?? 0);
        $orderAmount = $subtotalWithTax + $deliveryFee;

        if (!$coupon->canBeAppliedToAmount($orderAmount)) {
            return false;
        }

        $discount = $coupon->calculateDiscount($orderAmount);
        
        $this->coupon = $coupon;
        $this->discountAmount = number_format($discount, 2, '.', '');
        $this->recalculateTotals();

        return true;
    }

    /**
     * Remove the coupon from the order
     */
    public function removeCoupon(): void
    {
        $this->coupon = null;
        $this->discountAmount = '0.00';
        $this->recalculateTotals();
    }

    /**
     * Recalculates order totals based on all items
     */
    public function recalculateTotals(): void
    {
        $subtotalWithTax = 0; // Amount including taxes
        
        foreach ($this->items as $item) {
            $item->recalculateTotal();
            $subtotalWithTax += (float) $item->getTotal();
        }
        
        // Menu prices already include taxes (TTC)
        // Calculate amount without taxes (HT) and tax separately
        $taxRate = 0.10; // 10% VAT - standard rate used
        $subtotalWithoutTax = $subtotalWithTax / (1 + $taxRate);
        $taxAmount = $subtotalWithTax - $subtotalWithoutTax;
        
        $this->subtotal = number_format($subtotalWithoutTax, 2, '.', '');
        $this->taxAmount = number_format($taxAmount, 2, '.', '');
        
        // Calculate discount if coupon is applied
        $discount = 0;
        if ($this->coupon !== null) {
            $deliveryFee = (float) ($this->deliveryFee ?? 0);
            $orderAmount = $subtotalWithTax + $deliveryFee;
            $discount = $this->coupon->calculateDiscount($orderAmount);
            $this->discountAmount = number_format($discount, 2, '.', '');
        }
        
        // Total amount (subtotal + taxes + delivery fees - discount)
        $deliveryFee = (float) ($this->deliveryFee ?? 0);
        $total = $subtotalWithTax + $deliveryFee - $discount;
        $this->total = number_format($total, 2, '.', '');
    }
}
