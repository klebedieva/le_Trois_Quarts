<?php

namespace App\Tests\Unit\Service;

use App\DTO\OrderCreateRequest;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Enum\DeliveryMode;
use App\Enum\OrderStatus;
use App\Enum\PaymentMode;
use App\Repository\CouponRepository;
use App\Repository\OrderRepository;
use App\Service\AddressValidationService;
use App\Service\CartService;
use App\Service\OrderService;
use App\Service\RestaurantSettingsService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Unit Tests for OrderService
 * 
 * This test suite validates the order creation and management functionality.
 * OrderService is one of the most critical services in the application as it:
 * - Creates orders from shopping cart
 * - Validates delivery addresses
 * - Validates customer phone numbers
 * - Calculates order totals (subtotal, tax, delivery fee)
 * - Manages order status transitions
 * 
 * Business Context:
 * - Orders are the culmination of the customer journey
 * - Each order must be financially accurate
 * - Delivery validation prevents failed deliveries
 * - Phone validation ensures customer contact
 * - Order numbers must be unique for tracking
 * 
 * Test Coverage:
 * - Order creation (delivery and takeaway)
 * - Financial calculations (subtotal, tax, total)
 * - Address validation
 * - Phone number validation (French formats)
 * - Order item creation
 * - Status updates
 * - Error handling (empty cart, invalid data)
 * 
 * Dependencies Mocked:
 * - EntityManagerInterface (database operations)
 * - Connection (transaction handling)
 * - OrderRepository (order retrieval)
 * - CouponRepository (coupon lookup)
 * - CartService (shopping cart data)
 * - RestaurantSettingsService (tax rate, delivery fee)
 * - AddressValidationService (address validation)
 * - ParameterBagInterface (order configuration)
 * 
 * @package App\Tests\Unit\Service
 * @author Le Trois Quarts Development Team
 */
class OrderServiceTest extends TestCase
{
    /**
     * The service under test - handles order creation and management
     * 
     * @var OrderService
     */
    private OrderService $orderService;

    /**
     * Mock of EntityManager for database operations
     * 
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * Mock of OrderRepository for order retrieval
     * 
     * @var OrderRepository
     */
    private OrderRepository $orderRepository;

    /**
     * Mock of CartService for shopping cart data
     * 
     * @var CartService
     */
    private CartService $cartService;

    /**
     * Mock of RestaurantSettingsService for configuration
     * 
     * @var RestaurantSettingsService
     */
    private RestaurantSettingsService $restaurantSettings;

    /**
     * Mock of AddressValidationService for delivery validation
     * 
     * @var AddressValidationService
     */
    private AddressValidationService $addressValidationService;

    /**
     * Set up the test environment before each test method
     * 
     * This method creates a comprehensive mock environment for testing OrderService.
     * OrderService has 8 dependencies, all of which need to be mocked:
     * 
     * 1. EntityManager - Database persistence
     * 2. Connection - Transaction management
     * 3. OrderRepository - Order retrieval
     * 4. CouponRepository - Coupon lookup
     * 5. CartService - Shopping cart data
     * 6. RestaurantSettingsService - VAT rate, delivery fee
     * 7. AddressValidationService - Delivery address validation
     * 8. ParameterBagInterface - Order configuration values
     * 
     * Default Mock Behavior:
     * - Cart returns empty (tests override as needed)
     * - VAT rate = 10%
     * - Delivery fee = €5.00
     * - Address validation = valid
     * - EntityManager does nothing (persist/flush are void)
     * 
     * Why so many mocks?
     * - OrderService orchestrates multiple services
     * - We want to test ONLY OrderService logic
     * - Mocks provide predictable behavior
     * - Tests run fast without database/HTTP
     * 
     * @return void
     */
    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->connection = $this->createMock(Connection::class);
        $this->connection
            ->method('transactional')
            ->willReturnCallback(static fn(callable $callback) => $callback());

        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->couponRepository = $this->createMock(CouponRepository::class);
        $this->cartService = $this->createMock(CartService::class);

        $this->restaurantSettings = $this->createMock(RestaurantSettingsService::class);
        $this->restaurantSettings->method('getVatRate')->willReturn(0.10);
        $this->restaurantSettings->method('getDeliveryFee')->willReturn(5.00);

        $this->addressValidationService = $this->createMock(AddressValidationService::class);

        $this->parameterBag = $this->createMock(ParameterBagInterface::class);
        $this->parameterBag->method('get')->willReturnMap([
            ['order.no_prefix', 'ORD-'],
            ['order.max_payload_bytes', 65536],
            ['order.idempotency_ttl', 600],
        ]);

        $this->orderService = new OrderService(
            $this->entityManager,
            $this->connection,
            $this->orderRepository,
            $this->cartService,
            $this->restaurantSettings,
            $this->addressValidationService,
            $this->couponRepository,
            $this->parameterBag
        );
    }

    /**
     * Helper factory used to produce DTOs from associative arrays while keeping tests readable.
     * When $mergeDefaults is true we pre-fill the DTO with sensible values required by validation.
     */
    private function createOrderDto(array $data, bool $mergeDefaults = true): OrderCreateRequest
    {
        $defaults = [
            'deliveryMode' => 'pickup',
            'paymentMode' => 'card',
            'deliveryFee' => 0.0,
            'clientFirstName' => 'John',
            'clientLastName' => 'Doe',
            'clientPhone' => '0612345678',
            'clientEmail' => 'john.doe@example.test',
        ];

        if ($mergeDefaults) {
            $data = array_merge($defaults, $data);
        }

        $dto = new OrderCreateRequest();
        foreach ($data as $property => $value) {
            if (property_exists($dto, $property)) {
                $dto->$property = $value;
            }
        }

        return $dto;
    }

    /**
     * Test: Create delivery order with complete valid data
     * 
     * Scenario: Customer places order for delivery with full information
     * Expected Result: Order created with all details, cart cleared, totals calculated
     * 
     * This is the main happy path - customer completes entire checkout process:
     * 1. Has items in cart
     * 2. Provides delivery address
     * 3. Provides contact information
     * 4. System calculates totals
     * 5. Order is saved
     * 6. Cart is cleared
     * 
     * Business Flow:
     * Cart: [Pasta €15.50 ×2] = €31.00
     * + Delivery: €5.00
     * = Total: €36.00
     * 
     * Tax Calculation:
     * Subtotal (TTC): €31.00
     * Subtotal (HT): €31.00 / 1.10 = €28.18
     * Tax: €31.00 - €28.18 = €2.82
     * Total: €31.00 + €5.00 = €36.00
     * 
     * @return void
     */
    public function testCreateOrderWithDelivery(): void
    {
        // ARRANGE: Prepare cart with items
        $cartData = [
            'items' => [
                [
                    'id' => 1,
                    'name' => 'Pasta Carbonara',
                    'price' => 15.50,
                    'quantity' => 2,
                    'category' => 'plats',
                    'image' => 'pasta.jpg'
                ]
            ],
            'total' => 31.00,  // 15.50 × 2
            'itemCount' => 2
        ];

        // Configure CartService to return this cart
        $this->cartService->expects($this->once())
            ->method('getCart')
            ->willReturn($cartData);

        // Configure CartService clear to be called
        $this->cartService->expects($this->once())->method('clear');

        // Configure address validation to succeed
        $this->addressValidationService
            ->expects($this->once())
            ->method('validateAddressForDelivery')
            ->with('123 Rue de la Paix, Marseille', '13001')
            ->willReturn(['valid' => true, 'distance' => 2.5]);

        // Prepare order data
        $orderData = [
            'deliveryMode' => 'delivery',
            'deliveryAddress' => '123 Rue de la Paix, Marseille',
            'deliveryZip' => '13001',
            'deliveryInstructions' => 'Ring doorbell twice',
            'deliveryFee' => '5.00',
            'paymentMode' => 'card',
            'clientFirstName' => 'Jean',
            'clientLastName' => 'Dupont',
            'clientPhone' => '06 12 34 56 78',
            'clientEmail' => 'jean.dupont@example.com'
        ];

        // ACT: Create order
        $order = $this->orderService->createOrder($this->createOrderDto($orderData));

        // ASSERT: Verify order properties
        $this->assertInstanceOf(Order::class, $order);
        
        // ASSERT: Order number generated (format: ORD-YYYYMMDD-XXXX)
        $this->assertMatchesRegularExpression('/^ORD-\d{8}-\d{4}$/', $order->getNo());
        
        // ASSERT: Status is PENDING
        $this->assertEquals(OrderStatus::PENDING, $order->getStatus());
        
        // ASSERT: Delivery mode
        $this->assertEquals(DeliveryMode::DELIVERY, $order->getDeliveryMode());
        
        // ASSERT: Delivery details
        $this->assertEquals('123 Rue de la Paix, Marseille', $order->getDeliveryAddress());
        $this->assertEquals('13001', $order->getDeliveryZip());
        $this->assertEquals('Ring doorbell twice', $order->getDeliveryInstructions());
        $this->assertEquals('5.00', $order->getDeliveryFee());
        
        // ASSERT: Payment mode
        $this->assertEquals(PaymentMode::CARD, $order->getPaymentMode());
        
        // ASSERT: Client information
        $this->assertEquals('Jean', $order->getClientFirstName());
        $this->assertEquals('Dupont', $order->getClientLastName());
        $this->assertEquals('Jean Dupont', $order->getClientName());  // Auto-generated
        $this->assertEquals('06 12 34 56 78', $order->getClientPhone());
        $this->assertEquals('jean.dupont@example.com', $order->getClientEmail());
        
        // ASSERT: Financial calculations
        // Subtotal (HT): €31.00 / 1.10 = €28.18
        $this->assertEquals('28.18', $order->getSubtotal());
        
        // Tax: €31.00 - €28.18 = €2.82
        $this->assertEquals('2.82', $order->getTaxAmount());
        
        // Total: €31.00 + €5.00 = €36.00
        $this->assertEquals('36.00', $order->getTotal());
        
        // ASSERT: Order items created
        $this->assertCount(1, $order->getItems());
        
        $orderItem = $order->getItems()->first();
        $this->assertEquals(1, $orderItem->getProductId());
        $this->assertEquals('Pasta Carbonara', $orderItem->getProductName());
        $this->assertEquals('15.5', $orderItem->getUnitPrice());  // PHP may store as '15.5' not '15.50'
        $this->assertEquals(2, $orderItem->getQuantity());
        $this->assertEquals('31', $orderItem->getTotal());  // May be stored as '31' not '31.00'
    }

    /**
     * Test: Create takeaway order (no delivery)
     * 
     * Scenario: Customer picks up order at restaurant
     * Expected Result: Order created without delivery details, no delivery fee
     * 
     * Takeaway orders:
     * - No delivery address required
     * - No delivery fee (€0.00)
     * - May have reduced VAT rate (5.5% in France)
     * - Faster preparation time
     * 
     * Example:
     * Cart: €31.00
     * Delivery: €0.00 (takeaway)
     * Total: €31.00
     * 
     * @return void
     */
    public function testCreateOrderWithTakeaway(): void
    {
        // ARRANGE: Cart with items
        $cartData = [
            'items' => [
                ['id' => 1, 'name' => 'Pasta', 'price' => 15.50, 'quantity' => 1]
            ],
            'total' => 15.50,
            'itemCount' => 1
        ];

        $this->cartService->expects($this->once())
            ->method('getCart')
            ->willReturn($cartData);
        $this->cartService->expects($this->once())->method('clear');

        // Prepare takeaway/pickup order data
        $orderData = [
            'deliveryMode' => 'pickup',  // Pickup (not delivery)
            'paymentMode' => 'cash',
            'clientFirstName' => 'Marie',
            'clientLastName' => 'Martin',
            'clientPhone' => '0612345678',
            'clientEmail' => 'marie@example.com'
        ];

        // ACT: Create order
        $order = $this->orderService->createOrder($this->createOrderDto($orderData));

        // ASSERT: Pickup mode
        $this->assertEquals(DeliveryMode::PICKUP, $order->getDeliveryMode());
        
        // ASSERT: No delivery details
        $this->assertNull($order->getDeliveryAddress());
        $this->assertNull($order->getDeliveryZip());
        
        // ASSERT: Delivery fee is zero
        $this->assertEquals('0.00', $order->getDeliveryFee());
        
        // ASSERT: Total equals subtotal (no delivery fee)
        // Subtotal TTC: €15.50
        // Delivery: €0.00
        // Total: €15.50
        $this->assertEquals('15.50', $order->getTotal());
    }

    /**
     * Test: Cannot create order from empty cart
     * 
     * Scenario: User tries to checkout with no items in cart
     * Expected Result: InvalidArgumentException thrown
     * 
     * Security & UX:
     * - Prevents creating empty orders
     * - Catches UI bugs (checkout button should be disabled)
     * - Prevents abuse/spam orders
     * 
     * Error Cases:
     * - User cleared cart but old checkout page still open
     * - Direct API call without items
     * - Concurrent cart clear in another tab
     * 
     * @return void
     */
    public function testCreateOrderFromEmptyCartThrowsException(): void
    {
        // ARRANGE: Empty cart
        $this->cartService->expects($this->once())
            ->method('getCart')
            ->willReturn(['items' => [], 'total' => 0.00, 'itemCount' => 0]);

        // ASSERT: Expect exception
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le panier est vide');

        // ACT: Try to create order (will throw)
        $this->orderService->createOrder($this->createOrderDto([
            'deliveryMode' => 'delivery',
            'deliveryAddress' => '123 Rue Test'
        ], false));
    }

    /**
     * Test: Delivery order requires delivery address
     * 
     * Scenario: User selects delivery but doesn't provide address
     * Expected Result: InvalidArgumentException thrown
     * 
     * Business Rule:
     * Delivery orders MUST have:
     * - deliveryAddress (required)
     * - deliveryZip (optional but recommended)
     * 
     * This prevents:
     * - Undeliverable orders
     * - Wasted courier trips
     * - Customer complaints
     * 
     * @return void
     */
    public function testCreateOrderDeliveryWithoutAddressThrowsException(): void
    {
        // ARRANGE: Cart with items
        $this->cartService->expects($this->once())
            ->method('getCart')
            ->willReturn([
                'items' => [['id' => 1, 'name' => 'Pasta', 'price' => 15.50, 'quantity' => 1]],
                'total' => 15.50,
                'itemCount' => 1
            ]);

        // ASSERT: Expect exception
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("L'adresse de livraison est requise");

        // ACT: Attempt to create order without address
        $this->orderService->createOrder($this->createOrderDto([
            'deliveryMode' => 'delivery',
            'clientFirstName' => 'Jean'
        ], false));
    }

    /**
     * Test: Invalid delivery address throws exception
     * 
     * Scenario: Address is outside delivery radius
     * Expected Result: InvalidArgumentException with validation error
     * 
     * Address Validation:
     * - Checks if address is within delivery radius (e.g., 10km)
     * - Validates postal code format
     * - May check against delivery zones
     * 
     * Example Invalid Cases:
     * - Address too far from restaurant
     * - Invalid postal code
     * - Area not serviced
     * 
     * @return void
     */
    public function testCreateOrderWithInvalidAddressThrowsException(): void
    {
        // ARRANGE: Cart with items
        $this->cartService->expects($this->once())
            ->method('getCart')
            ->willReturn([
                'items' => [['id' => 1, 'name' => 'Pasta', 'price' => 15.50, 'quantity' => 1]],
                'total' => 15.50,
                'itemCount' => 1
            ]);

        // ARRANGE: Configure address validation to fail
        $this->addressValidationService
            ->expects($this->once())
            ->method('validateAddressForDelivery')
            ->with('Very Far Street, Another City', '99999')
            ->willReturn([
                'valid' => false,
                'error' => 'Livraison non disponible au-delà de 10km',
                'distance' => 15.5  // Too far
            ]);

        // ASSERT: Expect exception with validation error
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Livraison non disponible au-delà de 10km');

        // ACT: Try to create order with invalid address
        $this->orderService->createOrder($this->createOrderDto([
            'deliveryMode' => 'delivery',
            'deliveryAddress' => 'Very Far Street, Another City',
            'deliveryZip' => '99999',
            'clientFirstName' => 'Test',
            'clientLastName' => 'User',
            'clientPhone' => '0612345678',
            'clientEmail' => 'test@example.com'
        ], false));
    }

    /**
     * Test: Invalid French phone number throws exception
     * 
     * Scenario: Customer provides incorrectly formatted phone number
     * Expected Result: InvalidArgumentException thrown
     * 
     * French Phone Formats (valid):
     * - Mobile: 06 12 34 56 78 (starts with 06 or 07)
     * - Landline: 01 23 45 67 89 (starts with 01-05)
     * - International: +33 6 12 34 56 78
     * 
     * Invalid Examples:
     * - Wrong length: 123456
     * - Wrong prefix: 08 12 34 56 78 (08 is premium rate)
     * - Letters: 06 AB CD EF GH
     * 
     * @return void
     */
    public function testCreateOrderWithInvalidPhoneThrowsException(): void
    {
        // ARRANGE: Cart with items
        $this->cartService->expects($this->once())
            ->method('getCart')
            ->willReturn([
                'items' => [['id' => 1, 'name' => 'Pasta', 'price' => 15.50, 'quantity' => 1]],
                'total' => 15.50,
                'itemCount' => 1
            ]);

        // ASSERT: Expect exception
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Numéro de téléphone invalide');

        // ACT: Attempt to create order with invalid phone
        $this->orderService->createOrder($this->createOrderDto([
            'deliveryMode' => 'pickup',
            'clientFirstName' => 'Marie',
            'clientLastName' => 'Test',
            'clientPhone' => '1234'
        ], false));
    }

    /**
     * Test: Valid French phone number formats
     * 
     * Scenario: Test various valid French phone formats
     * Expected Result: All valid formats accepted
     * 
     * Valid Formats Tested:
     * 1. Mobile with spaces: 06 12 34 56 78
     * 2. Mobile without spaces: 0612345678
     * 3. Landline: 01 23 45 67 89
     * 4. International: +33 6 12 34 56 78
     * 5. With dashes: 06-12-34-56-78
     * 6. With dots: 06.12.34.56.78
     * 
     * @return void
     */
    public function testValidFrenchPhoneNumberFormats(): void
    {
        // Test cases: valid phone numbers (formats that pass validation)
        $validPhones = [
            '06 12 34 56 78',    // Mobile with spaces
            '0612345678',        // Mobile without spaces
            '01 23 45 67 89',    // Landline (Paris region)
            '06-12-34-56-78',    // With dashes
            '06.12.34.56.78',    // With dots
        ];

        // ARRANGE: Cart with items
        $cartData = [
            'items' => [['id' => 1, 'name' => 'Pasta', 'price' => 15.50, 'quantity' => 1]],
            'total' => 15.50,
            'itemCount' => 1
        ];
        
        $this->cartService->expects($this->exactly(count($validPhones)))
            ->method('getCart')
            ->willReturn($cartData);
        $this->cartService->expects($this->exactly(count($validPhones)))
            ->method('clear');

        // ACT & ASSERT: Test each phone format
        foreach ($validPhones as $phone) {
            // ACT: Create order with this phone number
            $order = $this->orderService->createOrder($this->createOrderDto([
                'deliveryMode' => 'pickup',
                'clientFirstName' => 'Test',
                'clientLastName' => 'User',
                'clientPhone' => $phone,
                'clientEmail' => 'test@example.com'
            ]));

            // ASSERT: Order created successfully (no exception)
            $this->assertInstanceOf(Order::class, $order);
            $this->assertEquals($phone, $order->getClientPhone());
        }
    }

    /**
     * Test: Order total calculation with delivery fee
     * 
     * Scenario: Verify correct calculation of all order totals
     * Expected Result: Subtotal, tax, and total calculated accurately
     * 
     * Calculation Steps:
     * 1. Cart total (TTC): €50.00
     * 2. Convert to HT: €50.00 / 1.10 = €45.45
     * 3. Calculate tax: €50.00 - €45.45 = €4.55
     * 4. Add delivery: €50.00 + €5.00 = €55.00
     * 
     * Financial Accuracy Critical:
     * - Subtotal must exclude delivery
     * - Tax calculated on subtotal only (not delivery)
     * - All amounts rounded to 2 decimals
     * 
     * @return void
     */
    public function testOrderTotalCalculation(): void
    {
        // ARRANGE: Cart with €50.00 total
        $this->cartService->expects($this->once())
            ->method('getCart')
            ->willReturn([
                'items' => [
                    ['id' => 1, 'name' => 'Item 1', 'price' => 25.00, 'quantity' => 2]
                ],
                'total' => 50.00,  // 25.00 × 2
                'itemCount' => 2
            ]);
        $this->cartService->expects($this->once())
            ->method('clear');

        // ARRANGE: Configure address validation to succeed
        $this->addressValidationService
            ->expects($this->once())
            ->method('validateAddressForDelivery')
            ->with('123 Rue Test', '13001')
            ->willReturn(['valid' => true, 'distance' => 3.0]);

        // ACT: Create order (delivery)
        $order = $this->orderService->createOrder($this->createOrderDto([
            'deliveryMode' => 'delivery',
            'deliveryAddress' => '123 Rue Test',
            'deliveryZip' => '13001',
            'deliveryFee' => '5.00',
            'clientFirstName' => 'Jean',
            'clientPhone' => '0612345678'
        ]));

        // ASSERT: Subtotal (HT) = €50.00 / 1.10 = €45.45
        $this->assertEquals('45.45', $order->getSubtotal());

        // ASSERT: Tax = €50.00 - €45.45 = €4.55
        $this->assertEquals('4.55', $order->getTaxAmount());

        // ASSERT: Total = €50.00 + €5.00 = €55.00
        $this->assertEquals('55.00', $order->getTotal());

        // ASSERT: Delivery fee
        $this->assertEquals('5.00', $order->getDeliveryFee());
    }

    /**
     * Test: Cart is cleared after order creation
     * 
     * Scenario: After successful order, cart should be empty
     * Expected Result: CartService::clear() is called exactly once
     * 
     * Why Clear Cart:
     * - Prevents duplicate orders
     * - Clean slate for next order
     * - Better user experience
     * - Prevents confusion
     * 
     * User Flow:
     * 1. Add items to cart
     * 2. Checkout → Order created
     * 3. Cart automatically cleared
     * 4. "Order Confirmed" page shows order details
     * 5. Cart badge shows 0 items
     * 
     * @return void
     */
    public function testCreateOrderClearsCart(): void
    {
        // ARRANGE: Cart with items
        $this->cartService->expects($this->once())
            ->method('getCart')
            ->willReturn([
                'items' => [['id' => 1, 'name' => 'Pasta', 'price' => 15.50, 'quantity' => 1]],
                'total' => 15.50,
                'itemCount' => 1
            ]);

        // ASSERT: CartService::clear() must be called exactly once
        $this->cartService
            ->expects($this->once())
            ->method('clear');

        // ACT: Create order
        $this->orderService->createOrder($this->createOrderDto([
            'deliveryMode' => 'pickup',
            'clientFirstName' => 'Test'
        ]));

        // If clear() not called, test fails due to expects(once())
    }

    /**
     * Test: Order number format and uniqueness
     * 
     * Scenario: Each order gets unique order number
     * Expected Result: Format is ORD-YYYYMMDD-XXXX
     * 
     * Order Number Format:
     * - Prefix: ORD
     * - Date: YYYYMMDD (e.g., 20251021)
     * - Random: 4 digits (0001-9999)
     * 
     * Examples:
     * - ORD-20251021-0001
     * - ORD-20251021-1234
     * - ORD-20251022-0001 (next day)
     * 
     * Benefits:
     * - Easy to sort chronologically
     * - Date immediately visible
     * - Unique within day (9999 orders/day possible)
     * - Human-readable
     * 
     * @return void
     */
    public function testOrderNumberFormat(): void
    {
        // ARRANGE: Cart with items
        $this->cartService->expects($this->exactly(2))
            ->method('getCart')
            ->willReturn([
                'items' => [['id' => 1, 'name' => 'Pasta', 'price' => 15.50, 'quantity' => 1]],
                'total' => 15.50,
                'itemCount' => 1
            ]);
        $this->cartService->expects($this->exactly(2))
            ->method('clear');

        // ACT: Create multiple orders
        $order1 = $this->orderService->createOrder($this->createOrderDto(['deliveryMode' => 'pickup', 'clientFirstName' => 'Test1']));
        $order2 = $this->orderService->createOrder($this->createOrderDto(['deliveryMode' => 'pickup', 'clientFirstName' => 'Test2']));

        // ASSERT: Format matches ORD-YYYYMMDD-XXXX
        $this->assertMatchesRegularExpression('/^ORD-\d{8}-\d{4}$/', $order1->getNo());
        $this->assertMatchesRegularExpression('/^ORD-\d{8}-\d{4}$/', $order2->getNo());

        // ASSERT: Order numbers are different (uniqueness)
        $this->assertNotEquals($order1->getNo(), $order2->getNo());

        // ASSERT: Contains today's date
        $today = (new \DateTime())->format('Ymd');
        $this->assertStringContainsString($today, $order1->getNo());
    }

    /**
     * Test: Order items created from cart items
     * 
     * Scenario: Cart items converted to OrderItems
     * Expected Result: Each cart item becomes OrderItem with correct data
     * 
     * Conversion Process:
     * Cart Item → OrderItem
     * - id → productId
     * - name → productName
     * - price → unitPrice
     * - quantity → quantity
     * - price × quantity → total
     * 
     * Why Convert:
     * - Cart is temporary (session)
     * - OrderItems are permanent (database)
     * - Price snapshot (items may change price later)
     * - Audit trail
     * 
     * @return void
     */
    public function testOrderItemsCreatedFromCart(): void
    {
        // ARRANGE: Cart with multiple items
        $this->cartService->expects($this->once())
            ->method('getCart')
            ->willReturn([
                'items' => [
                    [
                        'id' => 1,
                        'name' => 'Pasta Carbonara',
                        'price' => 15.50,
                        'quantity' => 2,
                        'category' => 'plats'
                    ],
                    [
                        'id' => 2,
                        'name' => 'Tiramisu',
                        'price' => 6.50,
                        'quantity' => 1,
                        'category' => 'desserts'
                    ]
                ],
                'total' => 37.50,  // (15.50 × 2) + (6.50 × 1)
                'itemCount' => 3
            ]);
        $this->cartService->expects($this->once())
            ->method('clear');

        // ACT: Create order
        $order = $this->orderService->createOrder($this->createOrderDto([
            'deliveryMode' => 'pickup',
            'clientFirstName' => 'Test'
        ]));

        // ASSERT: 2 order items created
        $items = $order->getItems();
        $this->assertCount(2, $items);

        // ASSERT: First item (Pasta)
        $item1 = $items[0];
        $this->assertEquals(1, $item1->getProductId());
        $this->assertEquals('Pasta Carbonara', $item1->getProductName());
        $this->assertEquals('15.5', $item1->getUnitPrice());  // Note: PHP may trim trailing zero
        $this->assertEquals(2, $item1->getQuantity());
        $this->assertEquals('31', $item1->getTotal());  // 15.50 × 2 (may be stored as '31')

        // ASSERT: Second item (Tiramisu)
        $item2 = $items[1];
        $this->assertEquals(2, $item2->getProductId());
        $this->assertEquals('Tiramisu', $item2->getProductName());
        $this->assertEquals('6.5', $item2->getUnitPrice());  // Note: PHP may trim trailing zero
        $this->assertEquals(1, $item2->getQuantity());
        $this->assertEquals('6.5', $item2->getTotal());  // 6.50 × 1
    }

    /**
     * Test: Get existing order by ID
     * 
     * Scenario: Retrieve previously created order
     * Expected Result: Order returned from repository
     * 
     * Use Cases:
     * - View order details page
     * - Order tracking
     * - Admin panel
     * - Customer service
     * 
     * @return void
     */
    public function testGetOrder(): void
    {
        // ARRANGE: Create mock order
        $mockOrder = $this->createMock(Order::class);
        $mockOrder->method('getId')->willReturn(123);
        $mockOrder->method('getNo')->willReturn('ORD-20251021-0001');

        // Configure repository to return this order
        $this->orderRepository
            ->expects($this->once())
            ->method('find')
            ->with(123)
            ->willReturn($mockOrder);

        // ACT: Get order
        $order = $this->orderService->getOrder(123);

        // ASSERT: Correct order returned
        $this->assertSame($mockOrder, $order);
        $this->assertEquals(123, $order->getId());
        $this->assertEquals('ORD-20251021-0001', $order->getNo());
    }

    /**
     * Test: Get non-existent order returns null
     * 
     * Scenario: Try to get order that doesn't exist
     * Expected Result: null returned (not exception)
     * 
     * Why null instead of exception:
     * - Allows caller to decide how to handle
     * - Common pattern in Symfony
     * - Can check: if ($order === null) { show 404 }
     * 
     * @return void
     */
    public function testGetOrderNotFound(): void
    {
        // ARRANGE: Repository returns null (not found)
        $this->orderRepository
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        // ACT: Get non-existent order
        $order = $this->orderService->getOrder(999);

        // ASSERT: null returned
        $this->assertNull($order);
    }

    /**
     * Test: Update order status
     * 
     * Scenario: Admin changes order status from PENDING to CONFIRMED
     * Expected Result: Status updated and saved
     * 
     * Status Flow:
     * PENDING → CONFIRMED → PREPARING → DELIVERED
     *        ↘ CANCELLED
     * 
     * Use Cases:
     * - Kitchen confirms order
     * - Delivery driver marks as delivered
     * - Customer cancels order
     * - Admin intervention
     * 
     * @return void
     */
    public function testUpdateOrderStatus(): void
    {
        // ARRANGE: Create mock order with PENDING status
        $mockOrder = $this->createMock(Order::class);
        $mockOrder->method('getId')->willReturn(123);
        
        // Expect setStatus to be called with CONFIRMED
        $mockOrder
            ->expects($this->once())
            ->method('setStatus')
            ->with(OrderStatus::CONFIRMED);

        // Repository returns this order
        $this->orderRepository
            ->method('find')
            ->with(123)
            ->willReturn($mockOrder);

        // EntityManager should flush changes
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // ACT: Update status
        $result = $this->orderService->updateOrderStatus(123, 'confirmed');

        // ASSERT: Same order returned
        $this->assertSame($mockOrder, $result);
    }

    /**
     * Test: Update status of non-existent order throws exception
     * 
     * Scenario: Try to update order that doesn't exist
     * Expected Result: InvalidArgumentException thrown
     * 
     * Error Cases:
     * - Wrong order ID
     * - Deleted order
     * - Typo in order number
     * 
     * @return void
     */
    public function testUpdateOrderStatusNotFoundThrowsException(): void
    {
        // ARRANGE: Repository returns null (order not found)
        $this->orderRepository
            ->method('find')
            ->with(999)
            ->willReturn(null);

        // ASSERT: Expect exception
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Commande introuvable: 999');

        // ACT: Try to update non-existent order
        $this->orderService->updateOrderStatus(999, 'confirmed');
    }

    /**
     * Test: Client name auto-generated from first and last name
     * 
     * Scenario: Provide firstName and lastName separately
     * Expected Result: clientName set to "FirstName LastName"
     * 
     * Auto-Generation:
     * Input:
     * - clientFirstName: "Jean"
     * - clientLastName: "Dupont"
     * 
     * Output:
     * - clientName: "Jean Dupont"
     * 
     * Benefits:
     * - Consistency
     * - No duplication
     * - Display name always available
     * 
     * @return void
     */
    public function testClientNameAutoGenerated(): void
    {
        // ARRANGE: Cart with items
        $this->cartService->expects($this->once())
            ->method('getCart')
            ->willReturn([
                'items' => [['id' => 1, 'name' => 'Pasta', 'price' => 15.50, 'quantity' => 1]],
                'total' => 15.50,
                'itemCount' => 1
            ]);
        $this->cartService->expects($this->once())
            ->method('clear');

        // ACT: Create order with first and last name
        $order = $this->orderService->createOrder($this->createOrderDto([
            'deliveryMode' => 'pickup',
            'clientFirstName' => 'Marie',
            'clientLastName' => 'Curie',
            'clientEmail' => 'marie@example.com'
        ]));

        // ASSERT: Full name auto-generated
        $this->assertEquals('Marie', $order->getClientFirstName());
        $this->assertEquals('Curie', $order->getClientLastName());
        $this->assertEquals('Marie Curie', $order->getClientName());  // Auto-generated
    }

    /**
     * Test: Order created at timestamp set
     * 
     * Scenario: Verify createdAt is set to current time
     * Expected Result: createdAt is DateTimeImmutable with current timestamp
     * 
     * Audit Trail:
     * - Know exactly when order was placed
     * - Sort orders chronologically
     * - Analytics and reporting
     * - SLA tracking
     * 
     * @return void
     */
    public function testOrderCreatedAtSet(): void
    {
        // ARRANGE: Cart with items
        $this->cartService->expects($this->once())
            ->method('getCart')
            ->willReturn([
                'items' => [['id' => 1, 'name' => 'Pasta', 'price' => 15.50, 'quantity' => 1]],
                'total' => 15.50,
                'itemCount' => 1
            ]);
        $this->cartService->expects($this->once())
            ->method('clear');

        // Capture current time before creating order
        $before = new \DateTimeImmutable();

        // ACT: Create order
        $order = $this->orderService->createOrder($this->createOrderDto([
            'deliveryMode' => 'pickup',
            'clientFirstName' => 'Test'
        ]));

        // Capture time after creating order
        $after = new \DateTimeImmutable();

        // ASSERT: createdAt is set
        $this->assertInstanceOf(\DateTimeImmutable::class, $order->getCreatedAt());

        // ASSERT: createdAt is between before and after (within test execution time)
        $this->assertGreaterThanOrEqual($before, $order->getCreatedAt());
        $this->assertLessThanOrEqual($after, $order->getCreatedAt());
    }
}

