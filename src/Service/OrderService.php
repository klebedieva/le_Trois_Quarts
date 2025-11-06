<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Coupon;
use App\DTO\OrderCreateRequest;
use App\Enum\DeliveryMode;
use App\Enum\OrderStatus;
use App\Enum\PaymentMode;
use App\Repository\OrderRepository;
use App\Repository\CouponRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Strategy\Delivery\DeliveryStrategyFactory;
use App\Strategy\Pricing\PricingStrategyFactory;

/**
 * Order Management Service
 *
 * Handles order creation, validation, and retrieval. Coordinates between
 * cart service, delivery strategies, pricing strategies, and database persistence.
 *
 * Responsibilities:
 * - Create orders from cart contents
 * - Validate delivery addresses and customer information
 * - Apply delivery fees and calculate totals (via pricing strategy)
 * - Handle coupon application and discount calculation
 * - Generate unique order numbers
 * - Clear cart after successful order creation
 * - Retrieve order details by ID
 *
 * Uses Strategy pattern for delivery and pricing calculations.
 */
class OrderService
{
    // TAX_RATE moved to RestaurantSettingsService

    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrderRepository $orderRepository,
        private CartService $cartService,
        private RequestStack $requestStack,
        private RestaurantSettingsService $restaurantSettings,
        private AddressValidationService $addressValidationService,
        private CouponRepository $couponRepository,
        private DeliveryStrategyFactory $deliveryStrategies,
        private PricingStrategyFactory $pricingStrategies
    ) {}

    /**
     * Create a new order from current cart contents
     *
     * Validates cart is not empty, creates Order entity, applies delivery strategy
     * (validates address and populates delivery fields), calculates pricing via
     * pricing strategy, handles coupon application if provided, creates order items
     * from cart, and persists to database. Clears cart after successful creation.
     *
     * @param OrderCreateRequest $dto Validated order creation DTO
     * @return Order Created and persisted Order entity
     * @throws \InvalidArgumentException If cart is empty or validation fails
     */
    public function createOrder(OrderCreateRequest $dto): Order
    {
        // Validate cart is not empty
        $cart = $this->cartService->getCart();
        
        if (empty($cart['items'])) {
            throw new \InvalidArgumentException("Le panier est vide");
        }

        // Create Order entity with initial state
        $order = new Order();
        $order->setNo($this->generateOrderNumber());
        $order->setStatus(OrderStatus::PENDING);
        $order->setCreatedAt(new \DateTimeImmutable());

        // Set delivery mode (default to DELIVERY if not specified)
        $deliveryMode = isset($dto->deliveryMode) 
            ? DeliveryMode::from($dto->deliveryMode)
            : DeliveryMode::DELIVERY;
        $order->setDeliveryMode($deliveryMode);

        // Apply delivery strategy (validates address and populates delivery fields)
        $deliveryStrategy = $this->deliveryStrategies->forMode($deliveryMode);
        // Build a simple associative array from DTO for strategy compatibility
        $deliveryData = [
            'deliveryAddress' => $dto->deliveryAddress,
            'deliveryZip' => $dto->deliveryZip,
            'deliveryInstructions' => $dto->deliveryInstructions,
            'deliveryFee' => $dto->deliveryFee,
        ];
        $deliveryStrategy->validateAndPopulate($order, $deliveryData);

        // Set payment mode (default to CARD if not specified)
        $paymentMode = isset($dto->paymentMode) 
            ? PaymentMode::from($dto->paymentMode)
            : PaymentMode::CARD;
        $order->setPaymentMode($paymentMode);

        // Set client information
        $order->setClientFirstName($dto->clientFirstName ?? null);
        $order->setClientLastName($dto->clientLastName ?? null);
        
        // Validate French phone number format if provided
        $clientPhone = $dto->clientPhone ?? null;
        if ($clientPhone && !$this->validateFrenchPhoneNumber($clientPhone)) {
            throw new \InvalidArgumentException("Numéro de téléphone invalide");
        }
        $order->setClientPhone($clientPhone);
        
        $order->setClientEmail($dto->clientEmail ?? null);
        
        // Generate full name automatically if both first and last name are provided
        if ($order->getClientFirstName() && $order->getClientLastName()) {
            $order->setClientName($order->getClientFirstName() . ' ' . $order->getClientLastName());
        }

        // Calculate amounts via pricing strategy (subtotal, tax, total, delivery fee)
        $subtotalWithTax = $cart['total'];
        $this->pricingStrategies->default()->computeAndSetTotals($order, (float) $subtotalWithTax);

        // Handle coupon application if provided
        $discount = 0;
        if (isset($dto->couponId)) {
            $coupon = $this->couponRepository->find($dto->couponId);
            
            // Apply coupon if valid and applicable to order amount
            if ($coupon && $coupon->canBeAppliedToAmount((float) $order->getTotal())) {
                $discount = $coupon->calculateDiscount((float) $order->getTotal());
                $order->setCoupon($coupon);
                $order->setDiscountAmount(number_format($discount, 2, '.', ''));
                $newTotal = (float) $order->getTotal() - $discount;
                $order->setTotal(number_format($newTotal, 2, '.', ''));
            } elseif (isset($dto->discountAmount)) {
                // Fallback to direct discount amount if coupon is not valid
                $discount = (float) $dto->discountAmount;
                $order->setDiscountAmount(number_format($discount, 2, '.', ''));
                $newTotal = (float) $order->getTotal() - $discount;
                $order->setTotal(number_format($newTotal, 2, '.', ''));
            }
        }

        // Create order items from cart items (preserves item details at order time)
        foreach ($cart['items'] as $cartItem) {
            $orderItem = new OrderItem();
            $orderItem->setProductId($cartItem['id']);
            $orderItem->setProductName($cartItem['name']);
            $orderItem->setUnitPrice((string) $cartItem['price']);
            $orderItem->setQuantity($cartItem['quantity']);
            $orderItem->setTotal((string) ($cartItem['price'] * $cartItem['quantity']));
            $orderItem->setOrderRef($order);
            
            $order->addItem($orderItem);
        }

        // Persist order and items to database
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        // Clear cart after successful order creation
        $this->cartService->clear();

        return $order;
    }

    /**
     * Retrieve order by ID
     *
     * @param int $orderId Order entity ID
     * @return Order|null Order entity if found, null otherwise
     */
    public function getOrder(int $orderId): ?Order
    {
        return $this->orderRepository->find($orderId);
    }

    /**
     * Generate unique order number
     *
     * Format: ORD-YYYYMMDD-XXX where XXX is a 3-digit sequential number
     * for orders created on the same day. Ensures uniqueness and readability.
     *
     * @return string Unique order number (e.g., ORD-20250107-001)
     */
    private function generateOrderNumber(): string
    {
        $date = (new \DateTime())->format('Ymd');
        $random = str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        return "ORD-{$date}-{$random}";
    }

    /**
     * Update order status
     */
    public function updateOrderStatus(int $orderId, string $status): Order
    {
        $order = $this->getOrder($orderId);
        
        if (!$order) {
            throw new \InvalidArgumentException("Commande introuvable: $orderId");
        }

        $orderStatus = OrderStatus::from($status);
        $order->setStatus($orderStatus);
        
        $this->entityManager->flush();
        
        return $order;
    }

    /**
     * Validate French phone number
     */
    private function validateFrenchPhoneNumber(string $phone): bool
    {
        if (empty($phone)) {
            return false;
        }

        // Clean number (remove spaces, dashes, dots)
        $cleanPhone = preg_replace('/[\s\-\.]/', '', $phone);

        // Check length and general format first
        // National format: 0X XXXX XXXX (10 digits total, starts with 0)
        // International format: +33 X XX XX XX XX (12 characters, starts with +33)

        if (strlen($cleanPhone) === 10 && str_starts_with($cleanPhone, '0')) {
            // French national format: 0X XXXX XXXX
            if (!preg_match('/^0[1-9]\d{8}$/', $cleanPhone)) {
                return false;
            }
            
            // Check first digits for mobiles (06, 07) and landlines (01-05)
            $firstTwoDigits = substr($cleanPhone, 0, 2);
            $validPrefixes = ['06', '07', '01', '02', '03', '04', '05'];
            return in_array($firstTwoDigits, $validPrefixes, true);
            
        } elseif (strlen($cleanPhone) === 12 && str_starts_with($cleanPhone, '+33')) {
            // International format: +33 X XX XX XX XX
            if (!preg_match('/^\+33[1-9]\d{8}$/', $cleanPhone)) {
                return false;
            }
            
            // Extract number without country code (+33)
            $withoutCountryCode = substr($cleanPhone, 3); // Remove '+33'
            
            // Check first digits for mobiles (06, 07) and landlines (01-05)
            $firstTwoDigits = substr($withoutCountryCode, 0, 2);
            $validPrefixes = ['06', '07', '01', '02', '03', '04', '05'];
            return in_array($firstTwoDigits, $validPrefixes, true);
        }

        // If neither 10 digits with 0, nor 12 characters with +33, then invalid
        return false;
    }
}

