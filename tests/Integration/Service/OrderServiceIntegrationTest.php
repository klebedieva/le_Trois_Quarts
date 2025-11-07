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
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Integration tests for the real OrderService using the Symfony container.
 */
final class OrderServiceIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private Session $session;
    private CartService $cartService;
    private ParameterBagInterface $parameterBag;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $sqliteUrl = 'sqlite:///:memory:';
        putenv('DATABASE_URL=' . $sqliteUrl);
        $_ENV['DATABASE_URL'] = $sqliteUrl;
        $_SERVER['DATABASE_URL'] = $sqliteUrl;

        self::bootKernel();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);

        // Reset the schema before each test so we always start with a clean database state.
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        // Build a synthetic session stack – required because CartService stores data in the session.
        $this->session = new Session(new MockArraySessionStorage());
        $requestStack = new RequestStack();
        $request = new Request();
        $request->setSession($this->session);
        $requestStack->push($request);

        // Reuse the real repository and image resolver so the service under test behaves like in production.
        $menuItemRepository = $this->entityManager->getRepository(\App\Entity\MenuItem::class);
        $imageResolver = $container->get(MenuItemImageResolver::class);
        $this->cartService = new CartService($requestStack, $menuItemRepository, $imageResolver);

        $this->parameterBag = $container->get(ParameterBagInterface::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->entityManager->close();
        self::ensureKernelShutdown();
    }

    public function testCreateOrderWithCouponPersistsOrderAndClearsCart(): void
    {
        // --- Arrange -------------------------------------------------------------------------------------------------
        // Create a valid coupon entity so OrderService can apply a real discount.
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

        // Simulate an existing cart stored in the session – two dishes at 15€ each.
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

        // Build the DTO with customer info and coupon reference.
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
        $order = $orderService->createOrder($dto);

        // --- Assert -------------------------------------------------------------------------------------------------
        // Basic persistence expectations: the order must be stored and enriched with a number.
        self::assertNotNull($order->getId());
        self::assertMatchesRegularExpression('/^ORD-\d{8}-\d{4}$/', $order->getNo());
        self::assertSame(OrderStatus::PENDING, $order->getStatus(), 'New orders start in PENDING status.');
        self::assertSame('pickup', $order->getDeliveryMode()->value);
        self::assertSame('card', $order->getPaymentMode()->value);

        // Totals should reflect subtotal, VAT and coupon discount (30€ TTC -> 27€ after 10% off).
        self::assertSame('27.27', $order->getSubtotal());
        self::assertSame('2.73', $order->getTaxAmount());
        self::assertSame('27.00', $order->getTotal());
        self::assertSame('3.00', $order->getDiscountAmount());
        self::assertNotNull($order->getCoupon());
        self::assertSame(1, $order->getItems()->count(), 'Exactly one order item should be created.');

        // Cart service should have been cleared as the final step of order creation.
        $cartAfter = $this->cartService->getCart();
        self::assertSame(0, $cartAfter['itemCount']);
        self::assertSame([], $cartAfter['items']);
    }

    public function testCreateOrderThrowsExceptionWhenCartIsEmpty(): void
    {
        // --- Arrange -------------------------------------------------------------------------------------------------
        $this->cartService->clear();

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
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le panier est vide');

        // --- Act ----------------------------------------------------------------------------------------------------
        $orderService->createOrder($dto);
    }

    /**
     * Helper that wires the real collaborators while stubbing address validation (no external HTTP calls).
     */
    private function createOrderService(): OrderService
    {
        $restaurantSettings = static::getContainer()->get(RestaurantSettingsService::class);

        // Lightweight stub: always considers the address valid so distance logic does not interfere with the test.
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

        return new OrderService(
            $this->entityManager,
            $this->entityManager->getConnection(),
            $this->entityManager->getRepository(Order::class),
            $this->cartService,
            $restaurantSettings,
            $addressValidation,
            $this->entityManager->getRepository(Coupon::class),
            $this->parameterBag
        );
    }
}

