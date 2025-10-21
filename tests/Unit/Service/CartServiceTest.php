<?php

namespace App\Tests\Unit\Service;

use App\Entity\MenuItem;
use App\Repository\MenuItemRepository;
use App\Service\CartService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Unit Tests for CartService
 * 
 * This test suite validates the shopping cart functionality for the restaurant application.
 * The cart is session-based and handles adding, updating, removing items, and calculating totals.
 * 
 * Business Context:
 * - Shopping cart is critical for e-commerce functionality
 * - Session-based storage (no database until order creation)
 * - Must handle concurrent operations (same item added multiple times)
 * - Must calculate totals accurately for checkout
 * 
 * Test Coverage:
 * - Adding items to cart (new and existing)
 * - Updating item quantities
 * - Removing items from cart
 * - Calculating cart total
 * - Counting items in cart
 * - Clearing entire cart
 * - Error handling (non-existent items)
 * 
 * @package App\Tests\Unit\Service
 * @author Le Trois Quarts Development Team
 */
class CartServiceTest extends TestCase
{
    /**
     * The service under test - manages shopping cart operations
     * 
     * @var CartService
     */
    private CartService $cartService;

    /**
     * Mock of RequestStack to simulate Symfony request/session handling
     * 
     * @var RequestStack
     */
    private RequestStack $requestStack;

    /**
     * Mock of Session to simulate user session storage
     * 
     * @var SessionInterface
     */
    private SessionInterface $session;

    /**
     * Mock of MenuItemRepository to simulate database queries
     * 
     * @var MenuItemRepository
     */
    private MenuItemRepository $menuItemRepository;

    /**
     * Simulated cart data stored in "session"
     * 
     * @var array
     */
    private array $sessionCart = [];

    /**
     * Set up the test environment before each test method
     * 
     * This method creates a fully mocked environment for testing CartService.
     * We mock all dependencies to ensure true unit testing:
     * 
     * 1. RequestStack - Symfony's request context holder
     * 2. Session - User session for storing cart data
     * 3. MenuItemRepository - Database access for menu items
     * 
     * The session is configured to store/retrieve cart data from $this->sessionCart
     * array, simulating real session behavior without needing actual sessions.
     * 
     * Why mock the session?
     * - Sessions require HTTP context (not available in unit tests)
     * - We want tests to be fast and isolated
     * - We control the data flow completely
     * - No side effects between tests
     * 
     * @return void
     */
    protected function setUp(): void
    {
        // Reset session cart data before each test
        $this->sessionCart = [];

        // Create mock of MenuItemRepository (database access)
        $this->menuItemRepository = $this->createMock(MenuItemRepository::class);

        // Create mock of SessionInterface (user session)
        $this->session = $this->createMock(SessionInterface::class);

        // Configure session mock to store data in $this->sessionCart
        // When get('cart', []) is called, return current cart or empty array
        $this->session->method('get')
            ->willReturnCallback(function ($key, $default = null) {
                return $this->sessionCart[$key] ?? $default ?? [];
            });

        // When set('cart', $data) is called, store data in $this->sessionCart
        $this->session->method('set')
            ->willReturnCallback(function ($key, $value) {
                $this->sessionCart[$key] = $value;
            });

        // When remove('cart') is called, clear $this->sessionCart
        $this->session->method('remove')
            ->willReturnCallback(function ($key) {
                unset($this->sessionCart[$key]);
            });

        // Create mock of RequestStack
        $this->requestStack = $this->createMock(RequestStack::class);
        
        // Configure RequestStack to return our mocked session
        $this->requestStack->method('getSession')
            ->willReturn($this->session);

        // Create the service under test with mocked dependencies
        $this->cartService = new CartService(
            $this->requestStack,
            $this->menuItemRepository
        );
    }

    /**
     * Test: Add a new item to empty cart
     * 
     * Scenario: Customer adds their first item to an empty shopping cart
     * Expected Result: Cart contains 1 item with correct details and totals
     * 
     * This is the most common operation - adding the first item to start an order.
     * The service must:
     * 1. Fetch item details from repository
     * 2. Store item in session cart
     * 3. Return cart details with totals
     * 
     * Business Flow:
     * Customer sees "Pasta Carbonara €15.50" and clicks "Add to Cart"
     * → Service adds item to cart
     * → Cart badge updates to show "1 item"
     * → Total shows €15.50
     * 
     * @return void
     */
    public function testAddNewItemToCart(): void
    {
        // ARRANGE: Create a mock menu item (Pasta Carbonara)
        $menuItem = $this->createMockMenuItem(
            id: 1,
            name: 'Pasta Carbonara',
            price: '15.50',
            category: 'plats',
            image: 'pasta.jpg'
        );

        // Configure repository to return this item when ID 1 is requested
        $this->menuItemRepository
            ->expects($this->once())  // Verify repository is called exactly once
            ->method('find')
            ->with(1)  // With menu item ID = 1
            ->willReturn($menuItem);

        // ACT: Add item to cart
        $result = $this->cartService->add(menuItemId: 1, quantity: 1);

        // ASSERT: Verify cart structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('itemCount', $result);

        // ASSERT: Verify cart contains exactly 1 item
        $this->assertCount(1, $result['items']);

        // ASSERT: Verify item details are correct
        $item = $result['items'][0];
        $this->assertEquals(1, $item['id']);
        $this->assertEquals('Pasta Carbonara', $item['name']);
        $this->assertEquals(15.50, $item['price']);
        $this->assertEquals('plats', $item['category']);
        $this->assertEquals(1, $item['quantity']);

        // ASSERT: Verify totals are correct
        $this->assertEquals(15.50, $result['total']);
        $this->assertEquals(1, $result['itemCount']);
    }

    /**
     * Test: Add existing item increases quantity
     * 
     * Scenario: Customer adds same item twice (or clicks "Add to Cart" again)
     * Expected Result: Quantity increases, not a duplicate entry
     * 
     * This prevents cart clutter - if customer adds same dish twice,
     * we increase quantity instead of creating two separate entries.
     * 
     * Example:
     * Cart: [Pasta Carbonara x1]
     * Customer adds Pasta Carbonara again
     * Cart: [Pasta Carbonara x2] (NOT [Pasta Carbonara x1, Pasta Carbonara x1])
     * 
     * @return void
     */
    public function testAddExistingItemIncreasesQuantity(): void
    {
        // ARRANGE: Create mock menu item
        $menuItem = $this->createMockMenuItem(
            id: 1,
            name: 'Pasta Carbonara',
            price: '15.50',
            category: 'plats',
            image: 'pasta.jpg'
        );

        // Repository should be called only on first add (item cached in cart after)
        $this->menuItemRepository
            ->expects($this->once())  // Called only ONCE (not twice)
            ->method('find')
            ->with(1)
            ->willReturn($menuItem);

        // ACT: Add item first time
        $result1 = $this->cartService->add(1, 1);

        // ACT: Add same item second time
        $result2 = $this->cartService->add(1, 2);  // Add 2 more units

        // ASSERT: Still only 1 unique item in cart (not 2 entries)
        $this->assertCount(1, $result2['items']);

        // ASSERT: Quantity should be 3 (1 + 2)
        $this->assertEquals(3, $result2['items'][0]['quantity']);

        // ASSERT: Total should be €46.50 (15.50 × 3)
        $this->assertEquals(46.50, $result2['total']);
        $this->assertEquals(3, $result2['itemCount']);
    }

    /**
     * Test: Add multiple different items to cart
     * 
     * Scenario: Customer builds a complete meal with multiple dishes
     * Expected Result: Cart contains all items with correct individual and total amounts
     * 
     * Real-World Example:
     * Customer orders:
     * - Appetizer: Salad (€8.00)
     * - Main: Pasta (€15.50)
     * - Dessert: Tiramisu (€6.50)
     * Total: €30.00
     * 
     * @return void
     */
    public function testAddMultipleDifferentItems(): void
    {
        // ARRANGE: Create three different menu items
        $appetizer = $this->createMockMenuItem(1, 'Salad', '8.00', 'entrees', 'salad.jpg');
        $main = $this->createMockMenuItem(2, 'Pasta Carbonara', '15.50', 'plats', 'pasta.jpg');
        $dessert = $this->createMockMenuItem(3, 'Tiramisu', '6.50', 'desserts', 'tiramisu.jpg');

        // Configure repository to return correct item based on ID
        $this->menuItemRepository
            ->method('find')
            ->willReturnMap([
                [1, null, $appetizer],
                [2, null, $main],
                [3, null, $dessert],
            ]);

        // ACT: Add all three items to cart
        $this->cartService->add(1, 1);  // Salad ×1
        $this->cartService->add(2, 1);  // Pasta ×1
        $result = $this->cartService->add(3, 1);  // Tiramisu ×1

        // ASSERT: Cart should have 3 different items
        $this->assertCount(3, $result['items']);

        // ASSERT: Total should be €30.00 (8.00 + 15.50 + 6.50)
        $this->assertEquals(30.00, $result['total']);

        // ASSERT: Item count should be 3 (one of each)
        $this->assertEquals(3, $result['itemCount']);

        // ASSERT: Verify each item is present
        $itemNames = array_column($result['items'], 'name');
        $this->assertContains('Salad', $itemNames);
        $this->assertContains('Pasta Carbonara', $itemNames);
        $this->assertContains('Tiramisu', $itemNames);
    }

    /**
     * Test: Add non-existent item throws exception
     * 
     * Scenario: Malicious user or bug tries to add item that doesn't exist
     * Expected Result: InvalidArgumentException is thrown
     * 
     * Security/Data Integrity:
     * - Prevents adding fake items to cart
     * - Catches bugs early (wrong ID passed)
     * - Provides clear error message for debugging
     * 
     * Example Error Cases:
     * - Deleted menu item still in browser cache
     * - Manually crafted request with invalid ID
     * - Typo in item ID during development
     * 
     * @return void
     */
    public function testAddNonExistentItemThrowsException(): void
    {
        // ARRANGE: Configure repository to return null (item not found)
        $this->menuItemRepository
            ->expects($this->once())
            ->method('find')
            ->with(999)  // Non-existent ID
            ->willReturn(null);

        // ASSERT: Expect exception to be thrown
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Menu item not found: 999');

        // ACT: Try to add non-existent item
        $this->cartService->add(999, 1);
    }

    /**
     * Test: Update item quantity in cart
     * 
     * Scenario: Customer changes quantity from cart page (e.g., 1 → 3)
     * Expected Result: Quantity updated, total recalculated correctly
     * 
     * Use Cases:
     * - Customer realizes they need more portions
     * - Ordering for multiple people
     * - Cart page quantity selector changed
     * 
     * Example:
     * Cart: [Pasta ×1 = €15.50]
     * Update quantity to 3
     * Cart: [Pasta ×3 = €46.50]
     * 
     * @return void
     */
    public function testUpdateQuantity(): void
    {
        // ARRANGE: Add item to cart first
        $menuItem = $this->createMockMenuItem(1, 'Pasta', '15.50', 'plats', 'pasta.jpg');
        $this->menuItemRepository->method('find')->willReturn($menuItem);
        
        $this->cartService->add(1, 1);  // Start with quantity 1

        // ACT: Update quantity to 3
        $result = $this->cartService->updateQuantity(1, 3);

        // ASSERT: Quantity should be updated to 3
        $this->assertEquals(3, $result['items'][0]['quantity']);

        // ASSERT: Total should be €46.50 (15.50 × 3)
        $this->assertEquals(46.50, $result['total']);
        $this->assertEquals(3, $result['itemCount']);
    }

    /**
     * Test: Update quantity to zero removes item
     * 
     * Scenario: Customer sets quantity to 0 (common UX pattern)
     * Expected Result: Item is removed from cart entirely
     * 
     * UX Convention:
     * Many e-commerce sites allow "set quantity to 0" as a way to delete an item.
     * This is more intuitive than requiring a separate "Remove" button.
     * 
     * Example:
     * Cart: [Pasta ×2]
     * Set quantity to 0
     * Cart: [] (empty)
     * 
     * @return void
     */
    public function testUpdateQuantityToZeroRemovesItem(): void
    {
        // ARRANGE: Add item to cart
        $menuItem = $this->createMockMenuItem(1, 'Pasta', '15.50', 'plats', 'pasta.jpg');
        $this->menuItemRepository->method('find')->willReturn($menuItem);
        
        $this->cartService->add(1, 2);  // Start with quantity 2

        // ACT: Set quantity to 0
        $result = $this->cartService->updateQuantity(1, 0);

        // ASSERT: Cart should be empty
        $this->assertCount(0, $result['items']);
        $this->assertEquals(0.00, $result['total']);
        $this->assertEquals(0, $result['itemCount']);
    }

    /**
     * Test: Update quantity of non-existent item throws exception
     * 
     * Scenario: Trying to update item that's not in cart
     * Expected Result: InvalidArgumentException is thrown
     * 
     * Error Cases:
     * - Concurrent requests (item removed in another tab)
     * - Stale UI (item removed but page not refreshed)
     * - Direct API manipulation
     * 
     * @return void
     */
    public function testUpdateQuantityOfNonExistentItemThrowsException(): void
    {
        // ARRANGE: Empty cart (no items)

        // ASSERT: Expect exception
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cart item not found: 999');

        // ACT: Try to update non-existent item
        $this->cartService->updateQuantity(999, 5);
    }

    /**
     * Test: Remove item from cart
     * 
     * Scenario: Customer clicks "Remove" button next to cart item
     * Expected Result: Item is deleted, cart recalculated
     * 
     * Example:
     * Cart: [Salad €8, Pasta €15.50, Dessert €6.50] = €30
     * Remove Pasta
     * Cart: [Salad €8, Dessert €6.50] = €14.50
     * 
     * @return void
     */
    public function testRemoveItem(): void
    {
        // ARRANGE: Add multiple items to cart
        $item1 = $this->createMockMenuItem(1, 'Salad', '8.00', 'entrees', 'salad.jpg');
        $item2 = $this->createMockMenuItem(2, 'Pasta', '15.50', 'plats', 'pasta.jpg');
        
        $this->menuItemRepository->method('find')
            ->willReturnMap([
                [1, null, $item1],
                [2, null, $item2],
            ]);

        $this->cartService->add(1, 1);  // Salad
        $this->cartService->add(2, 1);  // Pasta

        // ACT: Remove pasta (ID 2)
        $result = $this->cartService->remove(2);

        // ASSERT: Cart should have only 1 item left (Salad)
        $this->assertCount(1, $result['items']);
        $this->assertEquals('Salad', $result['items'][0]['name']);

        // ASSERT: Total should be €8.00 (only salad remains)
        $this->assertEquals(8.00, $result['total']);
        $this->assertEquals(1, $result['itemCount']);
    }

    /**
     * Test: Remove non-existent item throws exception
     * 
     * Scenario: Trying to remove item that's not in cart
     * Expected Result: InvalidArgumentException is thrown
     * 
     * @return void
     */
    public function testRemoveNonExistentItemThrowsException(): void
    {
        // ARRANGE: Empty cart

        // ASSERT: Expect exception
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cart item not found: 999');

        // ACT: Try to remove non-existent item
        $this->cartService->remove(999);
    }

    /**
     * Test: Get cart contents
     * 
     * Scenario: Load cart page or check cart status
     * Expected Result: Returns all items with totals
     * 
     * Use Cases:
     * - Display cart page
     * - Show cart preview popup
     * - Update cart badge count
     * - AJAX cart refresh
     * 
     * @return void
     */
    public function testGetCart(): void
    {
        // ARRANGE: Add items to cart
        $menuItem = $this->createMockMenuItem(1, 'Pasta', '15.50', 'plats', 'pasta.jpg');
        $this->menuItemRepository->method('find')->willReturn($menuItem);
        
        $this->cartService->add(1, 2);  // Add 2 pasta

        // ACT: Get cart contents
        $result = $this->cartService->getCart();

        // ASSERT: Verify cart structure and data
        $this->assertIsArray($result);
        $this->assertCount(1, $result['items']);
        $this->assertEquals(31.00, $result['total']);  // 15.50 × 2
        $this->assertEquals(2, $result['itemCount']);
    }

    /**
     * Test: Get empty cart
     * 
     * Scenario: New user or after clearing cart
     * Expected Result: Empty cart with zero totals
     * 
     * @return void
     */
    public function testGetEmptyCart(): void
    {
        // ARRANGE: Empty cart (nothing added)

        // ACT: Get cart
        $result = $this->cartService->getCart();

        // ASSERT: Should return empty cart structure
        $this->assertIsArray($result);
        $this->assertCount(0, $result['items']);
        $this->assertEquals(0.00, $result['total']);
        $this->assertEquals(0, $result['itemCount']);
    }

    /**
     * Test: Clear entire cart
     * 
     * Scenario: User clicks "Clear Cart" or order is completed
     * Expected Result: All items removed, cart is empty
     * 
     * Use Cases:
     * - User wants to start over
     * - After successful order placement
     * - User changes mind about entire order
     * 
     * @return void
     */
    public function testClearCart(): void
    {
        // ARRANGE: Add items to cart
        $menuItem = $this->createMockMenuItem(1, 'Pasta', '15.50', 'plats', 'pasta.jpg');
        $this->menuItemRepository->method('find')->willReturn($menuItem);
        
        $this->cartService->add(1, 3);  // Add items

        // Verify cart has items before clearing
        $cartBefore = $this->cartService->getCart();
        $this->assertNotEmpty($cartBefore['items']);

        // ACT: Clear cart
        $result = $this->cartService->clear();

        // ASSERT: Cart should be completely empty
        $this->assertCount(0, $result['items']);
        $this->assertEquals(0.00, $result['total']);
        $this->assertEquals(0, $result['itemCount']);

        // ASSERT: Verify cart remains empty on subsequent get
        $cartAfter = $this->cartService->getCart();
        $this->assertCount(0, $cartAfter['items']);
    }

    /**
     * Test: Get item count
     * 
     * Scenario: Display badge on cart icon showing number of items
     * Expected Result: Correct total quantity across all items
     * 
     * Note: This counts TOTAL QUANTITY, not unique items
     * Example: [Pasta ×2, Salad ×1] = count of 3 (not 2)
     * 
     * @return void
     */
    public function testGetItemCount(): void
    {
        // ARRANGE: Add items with different quantities
        $item1 = $this->createMockMenuItem(1, 'Pasta', '15.50', 'plats', 'pasta.jpg');
        $item2 = $this->createMockMenuItem(2, 'Salad', '8.00', 'entrees', 'salad.jpg');
        
        $this->menuItemRepository->method('find')
            ->willReturnMap([
                [1, null, $item1],
                [2, null, $item2],
            ]);

        $this->cartService->add(1, 2);  // Pasta ×2
        $this->cartService->add(2, 1);  // Salad ×1

        // ACT: Get item count
        $count = $this->cartService->getItemCount();

        // ASSERT: Should return 3 (2 + 1), not 2 unique items
        $this->assertEquals(3, $count);
    }

    /**
     * Test: Get item count for empty cart
     * 
     * Scenario: No items in cart
     * Expected Result: Count is 0
     * 
     * @return void
     */
    public function testGetItemCountEmptyCart(): void
    {
        // ARRANGE: Empty cart

        // ACT: Get count
        $count = $this->cartService->getItemCount();

        // ASSERT: Should be 0
        $this->assertEquals(0, $count);
    }

    /**
     * Test: Calculate total price
     * 
     * Scenario: Calculate total amount for checkout
     * Expected Result: Correct sum of (price × quantity) for all items
     * 
     * Example:
     * [Pasta ×2 at €15.50 = €31.00]
     * [Salad ×1 at €8.00 = €8.00]
     * Total = €39.00
     * 
     * @return void
     */
    public function testGetTotal(): void
    {
        // ARRANGE: Add items
        $item1 = $this->createMockMenuItem(1, 'Pasta', '15.50', 'plats', 'pasta.jpg');
        $item2 = $this->createMockMenuItem(2, 'Salad', '8.00', 'entrees', 'salad.jpg');
        
        $this->menuItemRepository->method('find')
            ->willReturnMap([
                [1, null, $item1],
                [2, null, $item2],
            ]);

        $this->cartService->add(1, 2);  // €15.50 × 2 = €31.00
        $this->cartService->add(2, 1);  // €8.00 × 1 = €8.00

        // ACT: Get total
        $total = $this->cartService->getTotal();

        // ASSERT: Should be €39.00 (31.00 + 8.00)
        $this->assertEquals(39.00, $total);
    }

    /**
     * Test: Get total for empty cart
     * 
     * Scenario: Empty cart total
     * Expected Result: Total is €0.00
     * 
     * @return void
     */
    public function testGetTotalEmptyCart(): void
    {
        // ARRANGE: Empty cart

        // ACT: Get total
        $total = $this->cartService->getTotal();

        // ASSERT: Should be 0
        $this->assertEquals(0.00, $total);
    }

    /**
     * Test: Total calculation precision with decimals
     * 
     * Scenario: Ensure rounding works correctly for prices with cents
     * Expected Result: Total is correctly rounded to 2 decimal places
     * 
     * Financial Accuracy:
     * - Must handle decimal precision correctly
     * - Avoid floating-point errors
     * - Always round to 2 decimal places for currency
     * 
     * @return void
     */
    public function testTotalCalculationPrecision(): void
    {
        // ARRANGE: Items with decimal prices
        $item1 = $this->createMockMenuItem(1, 'Item 1', '10.99', 'plats', 'item.jpg');
        $item2 = $this->createMockMenuItem(2, 'Item 2', '5.49', 'entrees', 'item2.jpg');
        
        $this->menuItemRepository->method('find')
            ->willReturnMap([
                [1, null, $item1],
                [2, null, $item2],
            ]);

        $this->cartService->add(1, 3);  // €10.99 × 3 = €32.97
        $this->cartService->add(2, 2);  // €5.49 × 2 = €10.98

        // ACT: Get total
        $result = $this->cartService->getCart();

        // ASSERT: Total should be €43.95 (32.97 + 10.98), properly rounded
        $this->assertEquals(43.95, $result['total']);
    }

    /**
     * Helper method: Create a mock MenuItem entity
     * 
     * This helper creates a properly configured mock MenuItem object
     * that behaves like a real entity from the database.
     * 
     * @param int $id Menu item ID
     * @param string $name Dish name
     * @param string $price Price as string (database format)
     * @param string $category Category (entrees, plats, desserts)
     * @param string $image Image filename
     * @return MenuItem Mocked entity
     */
    private function createMockMenuItem(
        int $id,
        string $name,
        string $price,
        string $category,
        string $image
    ): MenuItem {
        $menuItem = $this->createMock(MenuItem::class);
        
        $menuItem->method('getId')->willReturn($id);
        $menuItem->method('getName')->willReturn($name);
        $menuItem->method('getPrice')->willReturn($price);
        $menuItem->method('getCategory')->willReturn($category);
        $menuItem->method('getImage')->willReturn($image);
        
        return $menuItem;
    }
}

