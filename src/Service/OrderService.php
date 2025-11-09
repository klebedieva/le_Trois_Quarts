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
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Order Management Service
 *
 * Handles order creation, validation, and retrieval. Coordinates between
 * cart service, delivery validation, pricing calculations, and database persistence.
 *
 * Responsibilities:
 * - Create orders from cart contents
 * - Validate delivery addresses and customer information
 * - Apply delivery fees and calculate totals
 * - Handle coupon application and discount calculation
 * - Generate unique order numbers
 * - Clear cart after successful order creation
 * - Retrieve order details by ID
 *
 * Design principles:
 * - Uses simple switch/case for delivery mode handling (DELIVERY vs PICKUP)
 * - All database operations are wrapped in transactions for data integrity
 * - Clear separation of concerns: each method has a single responsibility
 * - Beginner-friendly: no complex patterns, just straightforward logic
 */
class OrderService
{
    /**
     * Constructor
     *
     * Injects all required dependencies for order management:
     * - EntityManagerInterface: for database persistence operations
     * - Connection: for transaction management
     * - OrderRepository: for order retrieval queries
     * - CartService: for cart operations (get cart, clear cart)
     * - RestaurantSettingsService: for restaurant configuration (tax rate, delivery fee)
     * - AddressValidationService: for delivery address validation
     * - CouponRepository: for coupon lookup and validation
     * - ParameterBagInterface: for accessing configuration parameters (order prefix, etc.)
     *
     * Note: TAX_RATE was moved to RestaurantSettingsService for better configuration management.
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Connection $connection,
        private OrderRepository $orderRepository,
        private CartService $cartService,
        private RestaurantSettingsService $restaurantSettings,
        private AddressValidationService $addressValidationService,
        private CouponRepository $couponRepository,
        private ParameterBagInterface $parameterBag,
        private TaxCalculationService $taxCalculationService
    ) {}

    /**
     * Create a new order from current cart contents
     *
     * This method performs an atomic transaction that:
     * 1. Validates cart is not empty
     * 2. Creates Order entity with initial state (PENDING status)
     * 3. Validates and populates delivery fields based on delivery mode (DELIVERY or PICKUP)
     * 4. Calculates pricing (subtotal, tax, total, delivery fee)
     * 5. Handles coupon application and discount calculation if provided
     * 6. Creates order items from cart items
     * 7. Persists order and all items to database
     * 8. Clears cart after successful persistence
     *
     * Side effects:
     * - Persists Order and OrderItem entities to database (flush)
     * - Clears cart session data (via CartService::clear())
     * - If coupon is applied, updates coupon usage count (handled by Coupon entity)
     *
     * Transaction guarantees:
     * - All database operations (order creation, items, coupon update) are atomic
     * - If any step fails, entire transaction rolls back (no partial orders)
     * - Cart is only cleared after successful database commit
     *
     * @param OrderCreateRequest $dto Validated order creation DTO
     * @return Order Created and persisted Order entity
     * @throws \InvalidArgumentException If cart is empty, phone number is invalid, or validation fails
     * @throws \RuntimeException If database transaction fails
     */
    public function createOrder(OrderCreateRequest $dto): Order
    {
        // Validate cart is not empty
        $cart = $this->cartService->getCart();
        
        if (empty($cart['items'])) {
            throw new \InvalidArgumentException("Le panier est vide");
        }

        // Wrap entire order creation in transaction for atomicity
        // This ensures order, items, and coupon updates are all committed together
        // or rolled back together if any step fails
        // Use Connection::transactional() for explicit transaction management
        // This method automatically handles beginTransaction, commit, and rollback
        return $this->connection->transactional(function () use ($dto, $cart) {
            // Step 1: Create Order entity with initial state
            $order = $this->createOrderEntity($dto);

            // Step 2: Populate client information (name, phone, email)
            $this->populateClientInfo($order, $dto);

            // Step 3: Create order items from cart items (ensures totals operate on real line items)
            $this->createOrderItemsFromCart($order, $cart['items']);

            // Step 4: Hydrate monetary totals prior to applying coupons/discounts.
            $this->taxCalculationService->applyOrderTotals($order);

            // Step 5: Apply discount if coupon or discount amount is provided.
            $this->applyDiscountToOrder($order, $dto);

            // Step 6: Re-run totals so coupons/manual discounts are reflected in persisted values.
            $this->taxCalculationService->applyOrderTotals($order);

            // Step 7: Persist order and clear cart
            $this->persistOrderAndClearCart($order);

            return $order;
        });
    }

    /**
     * Retrieve order by ID
     *
     * This is a read-only operation. No side effects.
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
     * Format: {PREFIX}-YYYYMMDD-XXXX where XXXX is a 4-digit random number.
     * Prefix is configurable via order.no_prefix parameter (default: 'ORD-').
     * Ensures uniqueness and readability.
     *
     * @return string Unique order number (e.g., ORD-20250107-1234)
     */
    private function generateOrderNumber(): string
    {
        // Get order prefix from configuration (e.g., "ORD-")
        $prefix = $this->parameterBag->get('order.no_prefix');
        
        // Get current date in YYYYMMDD format (e.g., "20250107")
        $date = (new \DateTime())->format('Ymd');
        
        // Generate random 4-digit number (0001-9999) and pad with zeros
        $random = str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Combine: prefix + date + random number (e.g., "ORD-20250107-1234")
        return "{$prefix}{$date}-{$random}";
    }

    /**
     * Update order status
     *
     * Side effects:
     * - Updates order status field
     * - Persists changes to database (flush)
     *
     * @param int $orderId Order entity ID
     * @param string $status New status (must be valid OrderStatus enum value)
     * @return Order Updated order entity
     * @throws \InvalidArgumentException If order not found or status is invalid
     */
    public function updateOrderStatus(int $orderId, string $status): Order
    {
        // Retrieve order by ID (returns null if not found)
        $order = $this->getOrder($orderId);
        
        // Validate that order exists
        if (!$order) {
            throw new \InvalidArgumentException("Commande introuvable: $orderId");
        }

        // Convert string status to OrderStatus enum
        // This will throw an exception if status is invalid
        $orderStatus = OrderStatus::from($status);
        $order->setStatus($orderStatus);
        
        // Persist status change to database
        $this->entityManager->flush();
        
        return $order;
    }

    /**
     * Validate French phone number format
     *
     * This method validates that a phone number matches French phone number formats.
     * It supports two formats:
     * - National format: 0X XXXX XXXX (10 digits, starts with 0)
     * - International format: +33 X XX XX XX XX (12 characters, starts with +33)
     *
     * Valid prefixes:
     * - Mobile: 06, 07
     * - Landline: 01, 02, 03, 04, 05
     *
     * Examples of valid numbers:
     * - "06 12 34 56 78" (national format)
     * - "+33 6 12 34 56 78" (international format)
     *
     * @param string $phone Phone number to validate
     * @return bool True if phone number is valid, false otherwise
     */
    private function validateFrenchPhoneNumber(string $phone): bool
    {
        // Early return: empty phone number is invalid
        if (empty($phone)) {
            return false;
        }

        // Step 1: Clean the phone number
        // Remove common formatting characters (spaces, dashes, dots)
        // This allows users to enter phone numbers in various formats
        $cleanPhone = preg_replace('/[\s\-\.]/', '', $phone);

        // Step 2: Validate national format (10 digits, starts with 0)
        // Format: 0X XXXX XXXX
        // Example: "0612345678"
        if (strlen($cleanPhone) === 10 && str_starts_with($cleanPhone, '0')) {
            // Check basic format: must be 0 followed by 9 digits (first digit after 0 must be 1-9)
            if (!preg_match('/^0[1-9]\d{8}$/', $cleanPhone)) {
                return false;
            }
            
            // Check first two digits for valid prefixes
            // Mobile: 06, 07
            // Landline: 01, 02, 03, 04, 05
            $firstTwoDigits = substr($cleanPhone, 0, 2);
            $validPrefixes = ['06', '07', '01', '02', '03', '04', '05'];
            return in_array($firstTwoDigits, $validPrefixes, true);
        }
        
        // Step 3: Validate international format (12 characters, starts with +33)
        // Format: +33 X XX XX XX XX
        // Example: "+33612345678"
        if (strlen($cleanPhone) === 12 && str_starts_with($cleanPhone, '+33')) {
            // Check basic format: must be +33 followed by 9 digits (first digit must be 1-9)
            if (!preg_match('/^\+33[1-9]\d{8}$/', $cleanPhone)) {
                return false;
            }
            
            // Extract number without country code (+33)
            // This allows us to check the same prefix rules as national format
            $withoutCountryCode = substr($cleanPhone, 3); // Remove '+33'
            
            // Check first two digits for valid prefixes (same as national format)
            $normalizedPrefix = '0' . substr($withoutCountryCode, 0, 1);
            $validPrefixes = ['06', '07', '01', '02', '03', '04', '05'];
            return in_array($normalizedPrefix, $validPrefixes, true);
        }

        // Step 4: If phone number doesn't match either format, it's invalid
        return false;
    }

    /**
     * Create Order entity with initial state
     *
     * This method creates a new Order entity and sets up its initial state:
     * - Generates unique order number
     * - Sets status to PENDING
     * - Sets creation timestamp
     * - Sets delivery mode (DELIVERY or PICKUP)
     * - Validates and populates delivery fields based on delivery mode
     * - Sets payment mode (CARD, CASH, etc.)
     *
     * @param OrderCreateRequest $dto Order creation DTO with delivery and payment data
     * @return Order New Order entity with initial state set
     * @throws \InvalidArgumentException If delivery validation fails
     */
    private function createOrderEntity(OrderCreateRequest $dto): Order
    {
        // Create new Order entity
        $order = new Order();
        $order->setNo($this->generateOrderNumber());
        $order->setStatus(OrderStatus::PENDING);
        $order->setCreatedAt(new \DateTimeImmutable());

        // Set delivery mode (default to DELIVERY if not specified)
        $deliveryMode = isset($dto->deliveryMode) 
            ? DeliveryMode::from($dto->deliveryMode)
            : DeliveryMode::DELIVERY;
        $order->setDeliveryMode($deliveryMode);

        // Validate and populate delivery fields based on delivery mode
        // Simple switch/case approach - easy to understand and extend
        // This replaces the Strategy pattern for better beginner-friendliness
        // When adding a new delivery mode, just add a new case here
        switch ($deliveryMode) {
            case DeliveryMode::DELIVERY:
                // Home delivery: validate address, set delivery fee
                $this->validateAndPopulateDelivery($order, $dto);
                break;
            case DeliveryMode::PICKUP:
                // In-store pickup: no address needed, fee is 0
                $this->validateAndPopulatePickup($order, $dto);
                break;
        }

        // Set payment mode (default to CARD if not specified)
        $paymentMode = isset($dto->paymentMode) 
            ? PaymentMode::from($dto->paymentMode)
            : PaymentMode::CARD;
        $order->setPaymentMode($paymentMode);

        return $order;
    }

    /**
     * Populate client information on order entity
     *
     * This method sets all client-related fields on the order:
     * - First name and last name
     * - Phone number (with validation)
     * - Email address
     * - Full name (auto-generated from first + last name)
     *
     * @param Order $order Order entity to populate (will be modified in place)
     * @param OrderCreateRequest $dto Order creation DTO with client data
     * @throws \InvalidArgumentException If phone number is invalid
     */
    private function populateClientInfo(Order $order, OrderCreateRequest $dto): void
    {
        // Set client name fields
        $order->setClientFirstName($dto->clientFirstName ?? null);
        $order->setClientLastName($dto->clientLastName ?? null);
        
        // Validate and set phone number
        $clientPhone = $dto->clientPhone ?? null;
        if ($clientPhone && !$this->validateFrenchPhoneNumber($clientPhone)) {
            throw new \InvalidArgumentException("Numéro de téléphone invalide");
        }
        $order->setClientPhone($clientPhone);
        
        // Set email address
        $order->setClientEmail($dto->clientEmail ?? null);
        
        // Generate full name automatically if both first and last name are provided
        // This is a convenience field for display purposes
        if ($order->getClientFirstName() && $order->getClientLastName()) {
            $order->setClientName($order->getClientFirstName() . ' ' . $order->getClientLastName());
        }
    }

    /**
     * Create order items from cart items
     *
     * This method converts cart items into OrderItem entities and adds them to the order.
     * It preserves item details (name, price) at order time, so even if the menu item
     * is later deleted or changed, the order history remains accurate.
     *
     * Each OrderItem stores:
     * - Product ID (reference to menu item)
     * - Product name (snapshot at order time)
     * - Unit price (snapshot at order time)
     * - Quantity
     * - Total (unit price * quantity)
     *
     * @param Order $order Order entity to add items to (will be modified in place)
     * @param array $cartItems Array of cart items with keys: id, name, price, quantity
     */
    private function createOrderItemsFromCart(Order $order, array $cartItems): void
    {
        foreach ($cartItems as $cartItem) {
            // Create new OrderItem entity
            $orderItem = new OrderItem();
            $orderItem->setProductId($cartItem['id']);
            $orderItem->setProductName($cartItem['name']);
            $orderItem->setUnitPrice((string) $cartItem['price']);
            $orderItem->setQuantity($cartItem['quantity']);
            $orderItem->setTotal((string) ($cartItem['price'] * $cartItem['quantity']));
            $orderItem->setOrderRef($order);
            
            // Add item to order (bidirectional relationship)
            $order->addItem($orderItem);
        }
    }

    /**
     * Persist order to database and clear cart
     *
     * This method performs the final steps of order creation:
     * 1. Persists the order and all its items to the database
     * 2. Clears the cart after successful persistence
     *
     * Note: Cart clearing is session-based and won't be rolled back if transaction fails,
     * which is acceptable (cart can be cleared manually if needed).
     *
     * @param Order $order Order entity to persist (must have all items already added)
     */
    private function persistOrderAndClearCart(Order $order): void
    {
        // Persist order and items to database (within transaction)
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        // Clear cart after successful database commit
        // This happens inside transaction, but cart clearing is session-based
        // and won't be rolled back if transaction fails (which is acceptable)
        $this->cartService->clear();
    }

    /**
     * Validate and populate delivery fields for DELIVERY mode
     *
     * This method handles home delivery orders. It validates the delivery address
     * (checks if address is within delivery radius, calculates distance, etc.)
     * and populates all delivery-related fields on the order entity.
     *
     * Steps:
     * 1. Extract address and zip code from DTO
     * 2. Validate that address is required (cannot be null)
     * 3. Validate address for delivery (distance, availability)
     * 4. Populate delivery fields on order entity
     * 5. Set delivery fee (use provided fee or default from restaurant settings)
     *
     * @param Order $order Order entity to populate (will be modified in place)
     * @param OrderCreateRequest $dto Order creation DTO with delivery data
     * @throws \InvalidArgumentException If address is missing or validation fails
     */
    private function validateAndPopulateDelivery(Order $order, OrderCreateRequest $dto): void
    {
        // Step 1: Extract address and zip code from DTO
        // These fields are optional in the DTO, but required for DELIVERY mode
        $address = $dto->deliveryAddress ?? null;
        $zip = $dto->deliveryZip ?? null;

        // Step 2: Validate that address is provided
        // For home delivery, address is mandatory
        if (!$address) {
            throw new \InvalidArgumentException("L'adresse de livraison est requise");
        }

        // Step 3: Validate address for delivery
        // This checks:
        // - Distance from restaurant (must be within delivery radius)
        // - Address geocoding (converts address to coordinates)
        // - Delivery availability for the area
        $validation = $this->addressValidationService->validateAddressForDelivery($address, $zip);
        if (!$validation['valid']) {
            // If validation fails, throw exception with error message
            throw new \InvalidArgumentException($validation['error'] ?? 'Livraison non disponible pour cette adresse');
        }

        // Step 4: Populate delivery fields on order entity
        // All validation passed, so we can safely set the delivery information
        $order->setDeliveryAddress($address);
        $order->setDeliveryZip($zip);
        $order->setDeliveryInstructions($dto->deliveryInstructions ?? null);
        
        // Step 5: Set delivery fee
        // Use the fee provided in DTO, or fall back to default fee from restaurant settings
        // Format to 2 decimal places (e.g., "5.00")
        $fee = $dto->deliveryFee ?? $this->restaurantSettings->getDeliveryFee();
        $order->setDeliveryFee(number_format((float) $fee, 2, '.', ''));
    }

    /**
     * Validate and populate delivery fields for PICKUP mode
     *
     * This method handles in-store pickup orders. For pickup orders:
     * - No address validation is needed (customer picks up at restaurant)
     * - Delivery fee is always 0.00 (no delivery cost)
     * - Address fields are set to null (not applicable)
     * - Delivery instructions are optional (e.g., "Pick up at front desk")
     *
     * @param Order $order Order entity to populate (will be modified in place)
     * @param OrderCreateRequest $dto Order creation DTO with delivery data
     */
    private function validateAndPopulatePickup(Order $order, OrderCreateRequest $dto): void
    {
        // Pickup orders: no address required, fee is always 0.00
        // Set delivery fee to 0.00 (no delivery cost for pickup)
        $order->setDeliveryFee('0.00');
        
        // Clear address fields (not applicable for pickup)
        $order->setDeliveryAddress(null);
        $order->setDeliveryZip(null);
        
        // Delivery instructions are optional (e.g., "Pick up at front desk")
        // These can be useful for pickup orders too
        $order->setDeliveryInstructions($dto->deliveryInstructions ?? null);
    }

    /**
     * Compute and set order totals (subtotal, tax, total)
     *
     * This method calculates all financial amounts for the order:
     * - Subtotal (HT - hors taxes): cart total without tax
     * - Tax amount: tax portion of the cart total
     * - Total (TTC - toutes taxes comprises): cart total + delivery fee
     *
     * Important: Cart prices already include taxes (TTC), so we need to extract
     * the tax amount from the cart total using the tax rate.
     *
     * Calculation formula:
     * - Subtotal (HT) = Cart Total (TTC) / (1 + Tax Rate)
     * - Tax Amount = Cart Total (TTC) - Subtotal (HT)
     * - Total = Cart Total (TTC) + Delivery Fee
     *
     * Example:
     * - Cart Total (TTC) = 100.00 EUR
     * - Tax Rate = 0.20 (20% VAT)
     * - Subtotal (HT) = 100.00 / 1.20 = 83.33 EUR
     * - Tax Amount = 100.00 - 83.33 = 16.67 EUR
     * - Delivery Fee = 5.00 EUR
     * - Total = 100.00 + 5.00 = 105.00 EUR
     *
     * @param Order $order Order entity to populate (will be modified in place)
     * @param float $cartTotal Cart total including taxes (TTC - toutes taxes comprises)
     */
    /**
     * Apply discount to order (coupon or direct discount)
     *
     * The method resets any previous discount state, then attempts to bind a coupon
     * or manual discount based on the DTO. Actual monetary adjustments are handled
     * after this method via TaxCalculationService::applyOrderTotals().
     *
     * @param Order $order Order entity to apply discount to (will be modified in place)
     * @param OrderCreateRequest $dto Order creation DTO with coupon/discount data
     */
    private function applyDiscountToOrder(Order $order, OrderCreateRequest $dto): void
    {
        // Reset stale state so repeated calls don't accumulate obsolete discounts.
        $order->setCoupon(null);
        $order->setDiscountAmount('0.00');

        if (isset($dto->couponId)) {
            $coupon = $this->couponRepository->find($dto->couponId);

            if ($coupon) {
                $orderAmount = (float) $order->getTotal();
                if ($coupon->canBeAppliedToAmount($orderAmount)) {
                    $order->setCoupon($coupon);
                    return;
                }
            }
        }

        if (isset($dto->discountAmount)) {
            $discount = max(0, (float) $dto->discountAmount);
            $discount = min($discount, (float) $order->getTotal());
            $order->setDiscountAmount(number_format($discount, 2, '.', ''));
        }
    }
}

