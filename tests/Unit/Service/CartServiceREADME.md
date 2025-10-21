# 📚 CartServiceTest - Detailed Explanation

## 🎯 What is Being Tested?

`CartService` is a critical e-commerce service that manages the shopping cart functionality for the restaurant application. The cart is session-based (no database until order creation) and handles all customer shopping operations.

This service is essential for:
- ✅ **E-commerce functionality** - Core shopping experience
- ✅ **Order preparation** - Cart becomes order on checkout
- ✅ **User experience** - Real-time cart updates
- ✅ **Revenue generation** - Direct impact on sales

### Business Context

In the restaurant application, customers:
1. Browse menu items
2. Add items to cart (with quantities)
3. Update quantities or remove items
4. See live total calculations
5. Proceed to checkout (cart → order)

The cart must:
- Handle concurrent additions of same item
- Calculate totals accurately
- Maintain data in user session
- Validate items exist before adding
- Provide clear error messages

---

## 📖 Test Structure Overview

### Class: `CartServiceTest`

This test class contains **17 test methods** covering:
- ✅ Adding items (4 tests)
- ✅ Updating quantities (3 tests)
- ✅ Removing items (2 tests)
- ✅ Cart operations (3 tests)
- ✅ Calculations (5 tests)

**Total Coverage**: 62 assertions across 17 tests

**Dependencies Mocked**:
1. `RequestStack` - Symfony HTTP component
2. `SessionInterface` - User session storage
3. `MenuItemRepository` - Database access
4. `MenuItem` - Product entity

---

## 🔧 Test Setup and Configuration

### 1. `setUp()` Method - Test Environment Initialization

```php
protected function setUp(): void
{
    $this->sessionCart = [];
    $this->menuItemRepository = $this->createMock(MenuItemRepository::class);
    $this->session = $this->createMock(SessionInterface::class);
    
    // Configure session callbacks
    $this->session->method('get')->willReturnCallback(...);
    $this->session->method('set')->willReturnCallback(...);
    $this->session->method('remove')->willReturnCallback(...);
    
    $this->requestStack = $this->createMock(RequestStack::class);
    $this->requestStack->method('getSession')->willReturn($this->session);
    
    $this->cartService = new CartService($this->requestStack, $this->menuItemRepository);
}
```

**What Happens Here:**

1. **Session Cart Reset**: `$this->sessionCart = []` - Fresh state for each test
2. **Repository Mock**: Simulates database queries for menu items
3. **Session Mock**: Simulates PHP session without HTTP context
4. **RequestStack Mock**: Provides session access
5. **Service Creation**: Real CartService with mocked dependencies

**Advanced Session Mocking:**

The session mock uses callbacks to simulate real session behavior:

```php
// GET: Retrieve from simulated session
$this->session->method('get')->willReturnCallback(function ($key, $default) {
    return $this->sessionCart[$key] ?? $default;
});

// SET: Store in simulated session
$this->session->method('set')->willReturnCallback(function ($key, $value) {
    $this->sessionCart[$key] = $value;
});

// REMOVE: Clear from simulated session
$this->session->method('remove')->willReturnCallback(function ($key) {
    unset($this->sessionCart[$key]);
});
```

**Why This Approach?**
- ✅ **Realistic**: Behaves like real PHP session
- ✅ **Isolated**: No actual session needed
- ✅ **Controlled**: Full control over data
- ✅ **Fast**: No I/O operations
- ✅ **Clean**: No cleanup between tests

**Real-World Analogy:**

Think of it like testing a calculator app. You don't need a real calculator screen - you can simulate button presses and verify calculations. Similarly, we simulate session operations without needing actual HTTP sessions.

---

## 📋 Detailed Test Breakdown

### Test 1: `testAddNewItemToCart()`

**Purpose**: Add first item to empty cart

**Scenario**: Customer adds Pasta Carbonara (€15.50) to empty cart

**Business Flow**:
```
Customer sees menu: "Pasta Carbonara €15.50"
↓
Clicks "Add to Cart"
↓
Service fetches item details from database
↓
Stores in session: {1: {id: 1, name: "Pasta", price: 15.50, qty: 1}}
↓
Returns cart details: {items: [...], total: 15.50, itemCount: 1}
```

**What We Test**:

```php
// ARRANGE: Create mock menu item
$menuItem = $this->createMockMenuItem(
    id: 1,
    name: 'Pasta Carbonara',
    price: '15.50',
    category: 'plats',
    image: 'pasta.jpg'
);

// Repository should return this item when ID 1 is requested
$this->menuItemRepository
    ->expects($this->once())  // Called exactly once
    ->method('find')
    ->with(1)
    ->willReturn($menuItem);

// ACT: Add to cart
$result = $this->cartService->add(1, 1);

// ASSERT: Verify cart structure
$this->assertIsArray($result);
$this->assertArrayHasKey('items', $result);
$this->assertArrayHasKey('total', $result);
$this->assertArrayHasKey('itemCount', $result);

// ASSERT: Verify cart content
$this->assertCount(1, $result['items']);
$this->assertEquals(15.50, $result['total']);
```

**Why `expects($this->once())`?**

This verifies the repository is called exactly once. If called zero times or multiple times, the test fails. This ensures:
- Item data is actually fetched from database
- No redundant database queries
- Proper caching behavior

**Key Validations**:
- ✅ Cart has correct structure (items, total, itemCount)
- ✅ Item details are accurate (ID, name, price, category)
- ✅ Total is calculated correctly (€15.50)
- ✅ Item count is correct (1)

---

### Test 2: `testAddExistingItemIncreasesQuantity()`

**Purpose**: Adding same item twice increases quantity, not duplicate entries

**Scenario**: Customer adds Pasta, then adds more Pasta

**Expected Behavior**:
```
Cart before: [Pasta ×1]
Add Pasta ×2
Cart after:  [Pasta ×3]  ← Single entry, quantity increased

NOT: [Pasta ×1, Pasta ×2]  ← This would be wrong!
```

**Why This Matters**:

E-commerce best practice - prevent cart clutter:
- ❌ BAD: Amazon cart showing same item 10 times
- ✅ GOOD: Amazon cart showing item with "Qty: 10"

**What We Test**:

```php
// ARRANGE: Mock item
$menuItem = $this->createMockMenuItem(...);

// Repository called only ONCE (item cached in cart after first add)
$this->menuItemRepository
    ->expects($this->once())  // NOT twice!
    ->method('find')
    ->willReturn($menuItem);

// ACT: Add twice
$this->cartService->add(1, 1);  // First add
$result = $this->cartService->add(1, 2);  // Second add (2 more units)

// ASSERT: Still only 1 unique item
$this->assertCount(1, $result['items']);

// ASSERT: Quantity is 3 (1 + 2)
$this->assertEquals(3, $result['items'][0]['quantity']);

// ASSERT: Total is €46.50 (15.50 × 3)
$this->assertEquals(46.50, $result['total']);
```

**Technical Detail**:

The service checks if item already exists in cart:

```php
// Inside CartService::add()
if (isset($cart[$menuItemId])) {
    // Item exists - increase quantity
    $cart[$menuItemId]['quantity'] += $quantity;
} else {
    // New item - fetch from database
    $menuItem = $this->repository->find($menuItemId);
    $cart[$menuItemId] = [...];
}
```

Our test verifies:
- ✅ Second add doesn't query database (uses cached data)
- ✅ Quantity accumulates correctly
- ✅ No duplicate entries created
- ✅ Total recalculates properly

---

### Test 3: `testAddMultipleDifferentItems()`

**Purpose**: Build complete meal with multiple different dishes

**Scenario**: Customer orders full 3-course meal

**Real-World Example**:
```
Customer adds:
1. Appetizer: Salad (€8.00)
2. Main Course: Pasta Carbonara (€15.50)
3. Dessert: Tiramisu (€6.50)

Expected Cart:
Items: 3 different dishes
Total: €30.00
Count: 3
```

**What We Test**:

```php
// ARRANGE: Three different items
$appetizer = $this->createMockMenuItem(1, 'Salad', '8.00', ...);
$main = $this->createMockMenuItem(2, 'Pasta', '15.50', ...);
$dessert = $this->createMockMenuItem(3, 'Tiramisu', '6.50', ...);

// Configure repository to return correct item based on ID
$this->menuItemRepository
    ->method('find')
    ->willReturnMap([
        [1, null, $appetizer],  // ID 1 → Salad
        [2, null, $main],       // ID 2 → Pasta
        [3, null, $dessert],    // ID 3 → Tiramisu
    ]);

// ACT: Add all three
$this->cartService->add(1, 1);
$this->cartService->add(2, 1);
$result = $this->cartService->add(3, 1);

// ASSERT: 3 different items
$this->assertCount(3, $result['items']);

// ASSERT: Total €30.00
$this->assertEquals(30.00, $result['total']);

// ASSERT: All items present
$itemNames = array_column($result['items'], 'name');
$this->assertContains('Salad', $itemNames);
$this->assertContains('Pasta Carbonara', $itemNames);
$this->assertContains('Tiramisu', $itemNames);
```

**`willReturnMap()` Explained**:

This method allows different return values based on input:

```php
->willReturnMap([
    [input1, ..., return1],
    [input2, ..., return2],
])

When find(1) called → returns appetizer
When find(2) called → returns main
When find(3) called → returns dessert
```

**Validations**:
- ✅ Cart maintains multiple different items
- ✅ Each item has correct details
- ✅ Total is sum of all items
- ✅ No items missing or duplicated

---

### Test 4: `testAddNonExistentItemThrowsException()`

**Purpose**: Security - prevent adding fake/deleted items

**Scenario**: Malicious user tries to add item ID 999 (doesn't exist)

**Security Implications**:

Without this check, attackers could:
- Add fake items to cart
- Bypass pricing
- Create invalid orders
- Exploit system vulnerabilities

**What We Test**:

```php
// ARRANGE: Repository returns null (item not found)
$this->menuItemRepository
    ->expects($this->once())
    ->method('find')
    ->with(999)  // Non-existent ID
    ->willReturn(null);

// ASSERT: Expect exception
$this->expectException(\InvalidArgumentException::class);
$this->expectExceptionMessage('Menu item not found: 999');

// ACT: Try to add (will throw)
$this->cartService->add(999, 1);
```

**Exception Testing Pattern**:

```php
// 1. Declare expectation BEFORE action
$this->expectException(SomeException::class);

// 2. Optionally check message
$this->expectExceptionMessage('Expected message');

// 3. Execute code that should throw
$this->service->methodThatThrows();

// If no exception thrown, test FAILS
```

**Real-World Scenarios**:
- Deleted menu item still in browser cache
- Manually crafted HTTP request with fake ID
- Database inconsistency (item deleted during session)
- Developer typo in item ID

---

### Test 5: `testUpdateQuantity()`

**Purpose**: Change quantity from cart page

**Scenario**: Customer changes Pasta quantity from 1 to 3

**User Experience**:
```
Cart Page:
┌─────────────────┬─────┬────────┐
│ Item            │ Qty │ Total  │
├─────────────────┼─────┼────────┤
│ Pasta Carbonara │ [1▼]│ €15.50 │ ← User changes to 3
└─────────────────┴─────┴────────┘

After Update:
┌─────────────────┬─────┬────────┐
│ Item            │ Qty │ Total  │
├─────────────────┼─────┼────────┤
│ Pasta Carbonara │ [3▼]│ €46.50 │ ← Automatically updated
└─────────────────┴─────┴────────┘
```

**What We Test**:

```php
// ARRANGE: Add item first
$menuItem = $this->createMockMenuItem(1, 'Pasta', '15.50', ...);
$this->menuItemRepository->method('find')->willReturn($menuItem);
$this->cartService->add(1, 1);  // Start with qty 1

// ACT: Update to qty 3
$result = $this->cartService->updateQuantity(1, 3);

// ASSERT: Quantity updated
$this->assertEquals(3, $result['items'][0]['quantity']);

// ASSERT: Total recalculated (15.50 × 3 = 46.50)
$this->assertEquals(46.50, $result['total']);
```

**Service Logic**:

```php
// Inside CartService::updateQuantity()
if (!isset($cart[$menuItemId])) {
    throw new \InvalidArgumentException("Cart item not found");
}

if ($quantity <= 0) {
    unset($cart[$menuItemId]);  // Remove if zero
} else {
    $cart[$menuItemId]['quantity'] = $quantity;  // Update quantity
}
```

---

### Test 6: `testUpdateQuantityToZeroRemovesItem()`

**Purpose**: UX pattern - setting quantity to 0 deletes item

**E-commerce Convention**:

Many sites (Amazon, eBay, etc.) allow "Qty: 0" to remove items:
- More intuitive than separate "Remove" button
- Familiar pattern for users
- Reduces UI clutter

**What We Test**:

```php
// ARRANGE: Add item with qty 2
$this->cartService->add(1, 2);

// ACT: Set quantity to 0
$result = $this->cartService->updateQuantity(1, 0);

// ASSERT: Cart is empty
$this->assertCount(0, $result['items']);
$this->assertEquals(0.00, $result['total']);
```

**User Flow**:
```
Before: Cart [Pasta ×2] Total: €31.00
User sets quantity selector to 0
After:  Cart [] Total: €0.00
```

---

### Test 7: `testUpdateQuantityOfNonExistentItemThrowsException()`

**Purpose**: Error handling - can't update item not in cart

**Error Scenarios**:
- Concurrent sessions (item removed in another tab)
- Stale page (cart updated but page not refreshed)
- Direct API manipulation

**What We Test**:

```php
// ARRANGE: Empty cart

// ASSERT: Expect exception
$this->expectException(\InvalidArgumentException::class);
$this->expectExceptionMessage('Cart item not found: 999');

// ACT: Try to update non-existent item
$this->cartService->updateQuantity(999, 5);
```

---

### Test 8: `testRemoveItem()`

**Purpose**: Delete item from cart

**Scenario**: Customer removes Pasta from 3-item cart

**Before/After**:
```
Before:
- Salad    €8.00
- Pasta    €15.50  ← Remove this
- Dessert  €6.50
Total: €30.00

After:
- Salad    €8.00
- Dessert  €6.50
Total: €14.50
```

**What We Test**:

```php
// ARRANGE: Add 2 items
$item1 = $this->createMockMenuItem(1, 'Salad', '8.00', ...);
$item2 = $this->createMockMenuItem(2, 'Pasta', '15.50', ...);
$this->cartService->add(1, 1);
$this->cartService->add(2, 1);

// ACT: Remove item 2 (Pasta)
$result = $this->cartService->remove(2);

// ASSERT: Only 1 item left
$this->assertCount(1, $result['items']);
$this->assertEquals('Salad', $result['items'][0]['name']);

// ASSERT: Total recalculated
$this->assertEquals(8.00, $result['total']);
```

---

### Test 9: `testRemoveNonExistentItemThrowsException()`

**Purpose**: Can't remove item that's not in cart

**What We Test**:

```php
// ARRANGE: Empty cart

// ASSERT: Expect exception
$this->expectException(\InvalidArgumentException::class);
$this->expectExceptionMessage('Cart item not found: 999');

// ACT: Try to remove
$this->cartService->remove(999);
```

---

### Test 10: `testGetCart()`

**Purpose**: Retrieve current cart state

**Use Cases**:
- Display cart page
- Show cart preview popup
- Update cart badge
- AJAX cart refresh

**What We Test**:

```php
// ARRANGE: Add items
$menuItem = $this->createMockMenuItem(1, 'Pasta', '15.50', ...);
$this->cartService->add(1, 2);  // 2 pasta

// ACT: Get cart
$result = $this->cartService->getCart();

// ASSERT: Correct structure and data
$this->assertIsArray($result);
$this->assertCount(1, $result['items']);
$this->assertEquals(31.00, $result['total']);  // 15.50 × 2
$this->assertEquals(2, $result['itemCount']);
```

---

### Test 11: `testGetEmptyCart()`

**Purpose**: Empty cart returns proper empty structure

**Scenario**: New user or after checkout

**What We Test**:

```php
// ARRANGE: Nothing (empty cart)

// ACT: Get cart
$result = $this->cartService->getCart();

// ASSERT: Empty but valid structure
$this->assertIsArray($result);
$this->assertCount(0, $result['items']);
$this->assertEquals(0.00, $result['total']);
$this->assertEquals(0, $result['itemCount']);
```

**Why Test Empty State**:

Empty states are often overlooked but critical:
- First-time user experience
- After successful order
- After clearing cart
- Edge case in calculations

---

### Test 12: `testClearCart()`

**Purpose**: Remove all items at once

**Use Cases**:
- "Clear Cart" button
- After successful order placement
- User wants to start over

**What We Test**:

```php
// ARRANGE: Add items
$menuItem = $this->createMockMenuItem(1, 'Pasta', '15.50', ...);
$this->cartService->add(1, 3);

// Verify cart has items
$cartBefore = $this->cartService->getCart();
$this->assertNotEmpty($cartBefore['items']);

// ACT: Clear cart
$result = $this->cartService->clear();

// ASSERT: Completely empty
$this->assertCount(0, $result['items']);
$this->assertEquals(0.00, $result['total']);

// ASSERT: Remains empty on subsequent get
$cartAfter = $this->cartService->getCart();
$this->assertCount(0, $cartAfter['items']);
```

**Important**: Test verifies cart remains empty even after calling `getCart()` again. This ensures session is actually cleared, not just returning empty result.

---

### Test 13: `testGetItemCount()`

**Purpose**: Display cart badge number

**Important Distinction**:

```
getItemCount() returns TOTAL QUANTITY, not unique items

Example:
Cart: [Pasta ×2, Salad ×1, Dessert ×1]

Unique items: 3
Item count: 4  ← This is what we return

Badge shows: "4" (total items)
```

**What We Test**:

```php
// ARRANGE: Add items with different quantities
$item1 = $this->createMockMenuItem(1, 'Pasta', '15.50', ...);
$item2 = $this->createMockMenuItem(2, 'Salad', '8.00', ...);

$this->cartService->add(1, 2);  // Pasta ×2
$this->cartService->add(2, 1);  // Salad ×1

// ACT: Get count
$count = $this->cartService->getItemCount();

// ASSERT: Returns 3 (2 + 1), not 2 unique items
$this->assertEquals(3, $count);
```

**UI Example**:
```html
<button>
  🛒 Cart
  <span class="badge">3</span>  ← Shows total quantity
</button>
```

---

### Test 14: `testGetItemCountEmptyCart()`

**Purpose**: Empty cart count is zero

**What We Test**:

```php
// ARRANGE: Empty cart

// ACT: Get count
$count = $this->cartService->getItemCount();

// ASSERT: Zero
$this->assertEquals(0, $count);
```

**Edge Case Handling**:

Ensures `getItemCount()` doesn't crash on empty cart:
```php
// Service must handle:
foreach ($cart as $item) {  // Empty array
    $count += $item['quantity'];
}
// Returns 0, not null or error
```

---

### Test 15: `testGetTotal()`

**Purpose**: Calculate total cart value

**Formula**: `Total = Σ(price × quantity) for all items`

**What We Test**:

```php
// ARRANGE: Multiple items
$item1 = $this->createMockMenuItem(1, 'Pasta', '15.50', ...);
$item2 = $this->createMockMenuItem(2, 'Salad', '8.00', ...);

$this->cartService->add(1, 2);  // €15.50 × 2 = €31.00
$this->cartService->add(2, 1);  // €8.00 × 1 = €8.00

// ACT: Get total
$total = $this->cartService->getTotal();

// ASSERT: €39.00 (31.00 + 8.00)
$this->assertEquals(39.00, $total);
```

**Calculation Breakdown**:
```
Item 1: €15.50 × 2 = €31.00
Item 2: €8.00 × 1 = €8.00
─────────────────────────────
Total:             €39.00
```

---

### Test 16: `testGetTotalEmptyCart()`

**Purpose**: Empty cart total is €0.00

**What We Test**:

```php
// ARRANGE: Empty cart

// ACT: Get total
$total = $this->cartService->getTotal();

// ASSERT: Zero
$this->assertEquals(0.00, $total);
```

---

### Test 17: `testTotalCalculationPrecision()`

**Purpose**: Verify decimal precision in calculations

**Critical for Financial Accuracy**:

```
Problem: Floating-point arithmetic can be inaccurate
€10.99 × 3 = €32.96999999... (computer)
€10.99 × 3 = €32.97 (correct)

Solution: Proper rounding to 2 decimal places
```

**What We Test**:

```php
// ARRANGE: Items with decimal prices
$item1 = $this->createMockMenuItem(1, 'Item 1', '10.99', ...);
$item2 = $this->createMockMenuItem(2, 'Item 2', '5.49', ...);

$this->cartService->add(1, 3);  // €10.99 × 3 = €32.97
$this->cartService->add(2, 2);  // €5.49 × 2 = €10.98

// ACT: Get cart
$result = $this->cartService->getCart();

// ASSERT: Total is €43.95 (32.97 + 10.98), properly rounded
$this->assertEquals(43.95, $result['total']);
```

**Service Implementation**:

```php
// Inside CartService
$total = 0;
foreach ($cart as $item) {
    $total += $item['price'] * $item['quantity'];
}
return round($total, 2);  // Always 2 decimal places!
```

**Why This Matters**:

Financial calculations must be exact:
- € 0.01 difference × 1000 orders = €10 discrepancy
- Audit trail must match exactly
- Tax calculations depend on accuracy
- Legal requirement for financial systems

---

## 🎨 Testing Patterns Used

### AAA Pattern (Arrange-Act-Assert)

Every test follows this structure:

```php
public function testExample(): void
{
    // ARRANGE: Set up test data
    $menuItem = $this->createMockMenuItem(...);
    $this->menuItemRepository->method('find')->willReturn($menuItem);

    // ACT: Execute the method under test
    $result = $this->cartService->add(1, 1);

    // ASSERT: Verify the results
    $this->assertEquals(15.50, $result['total']);
}
```

**Benefits**:
- 📖 Clear structure
- 🔍 Easy to debug (see which part failed)
- 🎯 One logical assertion per test
- 🔄 Consistent across all tests

---

### Helper Method: `createMockMenuItem()`

**Purpose**: Reduce code duplication, improve readability

**Implementation**:

```php
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
```

**Usage**:

```php
// Without helper (verbose):
$menuItem = $this->createMock(MenuItem::class);
$menuItem->method('getId')->willReturn(1);
$menuItem->method('getName')->willReturn('Pasta');
$menuItem->method('getPrice')->willReturn('15.50');
$menuItem->method('getCategory')->willReturn('plats');
$menuItem->method('getImage')->willReturn('pasta.jpg');

// With helper (concise):
$menuItem = $this->createMockMenuItem(1, 'Pasta', '15.50', 'plats', 'pasta.jpg');
```

**Benefits**:
- ✅ DRY (Don't Repeat Yourself)
- ✅ Less code to maintain
- ✅ Easier to read
- ✅ Consistent mock setup

---

## 🚀 How to Run These Tests

### Run All CartService Tests
```bash
php bin/phpunit tests/Unit/Service/CartServiceTest.php
```

### With Readable Output
```bash
php bin/phpunit --testdox tests/Unit/Service/CartServiceTest.php
```

**Output**:
```
Cart Service
 ✔ Add new item to cart
 ✔ Add existing item increases quantity
 ✔ Add multiple different items
 ...
OK (17 tests, 62 assertions)
```

### Run a Specific Test
```bash
php bin/phpunit --filter testAddNewItemToCart tests/Unit/Service/CartServiceTest.php
```

### All Service Tests
```bash
php bin/phpunit tests/Unit/Service/ --testdox
```

---

## 📈 What We Achieved

### Coverage Statistics
- **17 test methods** covering all service operations
- **62 assertions** validating multiple aspects
- **100% method coverage** of CartService public methods
- **Multiple scenarios**: happy path, edge cases, errors

### Quality Assurance
✅ **Business logic** validated (quantity accumulation, no duplicates)  
✅ **Edge cases** handled (empty cart, zero quantity)  
✅ **Error handling** tested (non-existent items)  
✅ **Calculations** accurate (decimal precision)  
✅ **Session management** working (data persists)  
✅ **Real scenarios** covered (complete shopping flow)  

---

## 🎓 Key Concepts Learned

### 1. Session Mocking
Simulating PHP sessions without HTTP context:

```php
$this->session->method('get')->willReturnCallback(function ($key, $default) {
    return $this->sessionCart[$key] ?? $default;
});
```

### 2. Repository Mocking
Controlling database responses:

```php
$this->menuItemRepository
    ->method('find')
    ->willReturnMap([
        [1, null, $item1],
        [2, null, $item2],
    ]);
```

### 3. Exception Testing
Verifying error handling:

```php
$this->expectException(\InvalidArgumentException::class);
$this->expectExceptionMessage('Menu item not found: 999');
$this->cartService->add(999, 1);
```

### 4. Complex State Management
Testing stateful operations:

```php
$this->cartService->add(1, 1);   // State changes
$this->cartService->add(1, 2);   // State changes again
$result = $this->cartService->getCart();  // Verify final state
```

---

## 🔍 Why These Are True Unit Tests

### ✅ Isolation
- **No real database**: MenuItemRepository is mocked
- **No real sessions**: SessionInterface is mocked
- **No HTTP context**: RequestStack is mocked
- **No file system**: Image paths returned but not validated

### ✅ Speed
```
17 tests in 60ms = 3.5ms per test
```
Unit tests should be < 100ms per test. We're well under.

### ✅ Independence
- Each test has fresh state (`setUp()` runs before each)
- Tests can run in any order
- No shared state between tests
- Can run in parallel

### ✅ Repeatability
Same input always produces same output:
```php
add(id=1, qty=2) → {items: [...], total: 31.00, count: 2}
// Every single time, guaranteed
```

---

## 📊 Test Results Interpretation

### ✅ All Tests Pass
```
.................                                                 17 / 17 (100%)
OK (17 tests, 62 assertions)
```
- `.` = one successful test
- `17 / 17` = all tests passed
- `62 assertions` = 62 checks passed

### ❌ Test Failure
```
.F...............                                                 17 / 17 (100%)
FAILURES!
Tests: 17, Assertions: 61, Failures: 1.
```
- `F` = Failed test
- Shows expected vs actual values
- Stack trace to find issue

---

## 💡 Practical Examples

### Example 1: Complete Shopping Flow
```php
// 1. Add appetizer
$cart->add(1, 1);  // Salad €8.00

// 2. Add main course (twice)
$cart->add(2, 1);  // Pasta €15.50
$cart->add(2, 2);  // More pasta (now ×3)

// 3. Add dessert
$cart->add(3, 1);  // Tiramisu €6.50

// 4. Change mind, remove pasta
$cart->remove(2);

// 5. Check total
$total = $cart->getTotal();  // €14.50 (Salad + Tiramisu)

// 6. Proceed to checkout
// Cart becomes order...
```

### Example 2: Error Handling
```php
try {
    $cart->add(999, 1);  // Non-existent item
} catch (\InvalidArgumentException $e) {
    echo $e->getMessage();  // "Menu item not found: 999"
    // Show user-friendly error
}
```

---

## 🔗 Related Files

| File | Purpose |
|------|---------|
| `CartServiceTest.php` | The test file |
| `src/Service/CartService.php` | Service being tested |
| `src/Repository/MenuItemRepository.php` | Dependency (mocked) |
| `src/Entity/MenuItem.php` | Entity (mocked) |

---

## ⏭️ Next Steps

After mastering CartService tests:

1. ✅ **Study** the test file thoroughly
2. ✅ **Run** tests and observe output
3. ✅ **Modify** a test to see it fail
4. ✅ **Write** similar tests for other services
5. ✅ **Practice** mocking strategies

---

## ❓ FAQ

**Q: Why mock the session instead of using real sessions?**  
A: Real sessions require HTTP context, are slow, and make tests dependent on environment.

**Q: Why 17 tests for a cart service?**  
A: E-commerce is critical - bugs = lost revenue. Comprehensive testing prevents costly errors.

**Q: What's the difference between `getTotal()` and `getCart()['total']`?**  
A: `getTotal()` returns just the number, `getCart()` returns full cart data including total.

**Q: Why test empty cart scenarios?**  
A: Empty states are often overlooked but represent real user states (new user, after checkout).

**Q: How do I know if my tests are good?**  
A: Good tests: cover happy path + edge cases + errors + real scenarios. This suite does all four.

---

**Created**: October 21, 2025  
**Status**: ✅ All tests passing  
**PHPUnit Version**: 11.5.39  
**PHP Version**: 8.2.26  
**Test Execution Time**: ~60ms  
**Author**: Le Trois Quarts Development Team

---

🎉 **Happy Testing!**

