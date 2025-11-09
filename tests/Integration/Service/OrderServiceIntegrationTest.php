<?php

namespace App\Tests\Integration\Service;

use App\DTO\OrderCreateRequest;
use App\Entity\Coupon;
use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Service\CartService;
use App\Service\MenuItemImageResolver;
use App\Service\OrderService;
use App\Service\RestaurantSettingsService;
use App\Service\TaxCalculationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * This class runs integration tests against the real `OrderService`.
 * We boot Symfony's kernel, grab the real service container and interact with the database
 * so that the service is exercised exactly like it would be in production.
 * Think of it as a high-confidence test that spans multiple layers (database, services, repositories).
 */
final class OrderServiceIntegrationTest extends KernelTestCase
{
    /**
     * Real Doctrine entity manager pulled from the container.
     * It allows us to persist entities and query the database just like the application does.
     */
    private EntityManagerInterface $entityManager;

    /**
     * Fake (in-memory) HTTP session used so that `CartService` can store cart items.
     * Using `MockArraySessionStorage` means nothing ever hits disk.
     */
    private Session $session;

    /**
     * The real `CartService` from the project.
     * Even though this is a test, we want to exercise the production cart logic and only stub what is expensive.
     */
    private CartService $cartService;

    /**
     * Holds application configuration parameters. Injected into `OrderService`.
     */
    private ParameterBagInterface $parameterBag;

    protected function setUp(): void
    {
        // Always stop any previous kernel before booting a new one.
        // Integration tests share global state (the kernel instance), so we need to reset it each time.
        self::ensureKernelShutdown();

        // Use an in-memory SQLite database so tests stay fast and isolated.
        // We override environment variables to trick Doctrine into connecting to SQLite instead of MySQL/PostgreSQL.
        $sqliteUrl = 'sqlite:///:memory:';
        putenv('DATABASE_URL=' . $sqliteUrl);
        $_ENV['DATABASE_URL'] = $sqliteUrl;
        $_SERVER['DATABASE_URL'] = $sqliteUrl;

        // Boot Symfony and retrieve the service container.
        self::bootKernel();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);

        // Drop and recreate the database schema on every test run.
        // This ensures we never leak data between tests: each test starts from a blank slate.
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        // Build a fake HTTP request stack with an in-memory session.
        // `CartService` expects a request stack to read/write the cart from the session, so we simulate the bare minimum.
        $this->session = new Session(new MockArraySessionStorage());
        $requestStack = new RequestStack();
        $request = new Request();
        $request->setSession($this->session);
        $requestStack->push($request);

        // Use the real menu item repository + image resolver from the container.
        // This means the cart interacts with the actual database and image logic, keeping the test realistic.
        $menuItemRepository = $this->entityManager->getRepository(\App\Entity\MenuItem::class);
        $imageResolver = $container->get(MenuItemImageResolver::class);
        $this->cartService = new CartService($requestStack, $menuItemRepository, $imageResolver);

        $this->parameterBag = $container->get(ParameterBagInterface::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Closing the EntityManager frees up resources and avoids memory leaks between tests.
        $this->entityManager->close();
        self::ensureKernelShutdown();
    }

    public function testCreateOrderWithCouponPersistsOrderAndClearsCart(): void
    {
        // --- Arrange -------------------------------------------------------------------------------------------------
        // 1) Arrange: create a real coupon entity so we can test discount logic end-to-end.
        //    This is persisted in SQLite and will be used later via its database ID.
        $coupon = (new Coupon())
            ->setCode('WELCOME10')
            ->setDiscountType(Coupon::TYPE_PERCENTAGE)
            ->setDiscountValue('10')
            ->setMinOrderAmount('0')
            ->setValidFrom(new \DateTime('-1 day'))
            ->setValidUntil(new \DateTime('+1 day'))
            ->setIsActive(true);

        $this->entityManager->persist($coupon);
        $this->entityManager->flush();

        // 2) Seed the cart session with a realistic payload: two main dishes at 15€ each.
        //    We mimic exactly what the frontend would store so `CartService` reads familiar data.
        $this->session->set('cart', [
            1 => [
                'id' => 1,
                'name' => 'Pâtes maison',
                'price' => 15.0,
                'image' => '/uploads/menu/pates.jpg',
                'category' => 'plats',
                'quantity' => 2,
            ],
        ]);

        // 3) Build the DTO (Data Transfer Object) that carries the user's form submission.
        //    `OrderService::createOrder` expects this structure, so we fill it exactly as the controller would.
        $dto = new OrderCreateRequest();
        $dto->deliveryMode = 'pickup';
        $dto->paymentMode = 'card';
        $dto->deliveryFee = 0.0;
        $dto->clientFirstName = 'Jean';
        $dto->clientLastName = 'Dupont';
        $dto->clientPhone = '0612345678';
        $dto->clientEmail = 'jean.dupont@example.test';
        $dto->couponId = $coupon->getId();

        $orderService = $this->createOrderService();

        // --- Act ----------------------------------------------------------------------------------------------------
        // Call the real service – this will read the cart, validate the coupon, persist the order and clear the cart.
        $order = $orderService->createOrder($dto);

        // --- Assert -------------------------------------------------------------------------------------------------
        // Assert block verifies that the service did everything it promised:
        // * The order was persisted and assigned an auto-increment ID / business number.
        self::assertNotNull($order->getId());
        self::assertMatchesRegularExpression('/^ORD-\d{8}-\d{4}$/', $order->getNo());
        self::assertSame(OrderStatus::PENDING, $order->getStatus(), 'New orders start in PENDING status.');
        self::assertSame('pickup', $order->getDeliveryMode()->value);
        self::assertSame('card', $order->getPaymentMode()->value);

        // * Monetary values include VAT and coupon discount.
        //   Two items at 15€ each = 30€ TTC -> after a 10% discount we expect 27€ TTC.
        self::assertSame('27.27', $order->getSubtotal());
        self::assertSame('2.73', $order->getTaxAmount());
        self::assertSame('27.00', $order->getTotal());
        self::assertSame('3.00', $order->getDiscountAmount());
        self::assertNotNull($order->getCoupon());
        self::assertSame(1, $order->getItems()->count(), 'Exactly one order item should be created.');

        // * Critical side-effect: the cart must be cleared so the UI shows an empty basket.
        $cartAfter = $this->cartService->getCart();
        self::assertSame(0, $cartAfter['itemCount']);
        self::assertSame([], $cartAfter['items']);
    }

    public function testCreateOrderThrowsExceptionWhenCartIsEmpty(): void
    {
        // --- Arrange -------------------------------------------------------------------------------------------------
        // Start from an empty cart. We explicitly clear the cart to express the business pre-condition.
        $this->cartService->clear();

        // Build a DTO that looks valid from the customer's perspective.
        // Even with correct personal data, the service should reject the request because the cart is empty.
        $dto = new OrderCreateRequest();
        $dto->deliveryMode = 'pickup';
        $dto->paymentMode = 'card';
        $dto->deliveryFee = 0.0;
        $dto->clientFirstName = 'Marie';
        $dto->clientLastName = 'Curie';
        $dto->clientPhone = '0612345678';
        $dto->clientEmail = 'marie.curie@example.test';

        $orderService = $this->createOrderService();

        // --- Assert (Expect Exception) ----------------------------------------------------------------------------
        // When the cart is empty we want to throw an exception instead of silently creating an invalid order.
        // PHPUnit lets us declare that the next operation MUST throw an exception with a specific message.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le panier est vide');

        // --- Act ----------------------------------------------------------------------------------------------------
        $orderService->createOrder($dto);
    }

    /**
     * Helper that builds an `OrderService` instance ready for the tests.
     * We reuse all real collaborators except the address validation service, which normally calls an external API.
     * Replacing it with a simple anonymous class keeps the test deterministic and fast.
     */
    private function createOrderService(): OrderService
    {
        $restaurantSettings = static::getContainer()->get(RestaurantSettingsService::class);

        // Anonymous class extends `AddressValidationService` and overrides the methods we need.
        // The stub always returns "valid" to focus the tests on order creation rather than geocoding logic.
        $addressValidation = new class extends \App\Service\AddressValidationService {
            public function __construct() {}

            public function validateAddressForDelivery(string $address, string $zipCode = null): array
            {
                return ['valid' => true, 'error' => null, 'distance' => 0.0, 'coordinates' => ['lat' => 0.0, 'lng' => 0.0]];
            }

            public function validateZipCodeForDelivery(string $zipCode): array
            {
                return ['valid' => true, 'error' => null, 'distance' => 0.0];
            }
        };

        $taxCalculationService = static::getContainer()->get(TaxCalculationService::class);

        return new OrderService(
            $this->entityManager,
            $this->entityManager->getConnection(),
            $this->entityManager->getRepository(Order::class),
            $this->cartService,
            $restaurantSettings,
            $addressValidation,
            $this->entityManager->getRepository(Coupon::class),
            $this->parameterBag,
            $taxCalculationService
        );
    }
}

