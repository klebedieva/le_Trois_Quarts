# 📚 OrderServiceTest - Detailed Explanation

## 🎯 What is Being Tested?

`OrderService` is the **most critical service** in the application - it converts shopping carts into confirmed orders. This service orchestrates multiple subsystems and handles complex business logic including financial calculations, address validation, and phone number verification.

This service is essential for:
- ✅ **Order Creation** - Converting cart to permanent order
- ✅ **Financial Accuracy** - Calculating subtotals, tax, delivery fees
- ✅ **Delivery Management** - Validating addresses and delivery radius
- ✅ **Customer Validation** - French phone number format verification
- ✅ **Order Tracking** - Unique order numbers and status management
- ✅ **Business Logic** - Delivery vs pickup handling

### Business Context

OrderService is the culmination of the customer journey:

```
Customer Journey:
1. Browse menu                    (MenuController)
2. Add items to cart             (CartService)
3. Review cart                   (CartController)
4. Enter delivery info           (OrderController)
5. Validate & create order       (OrderService) ← We test this!
6. Payment processing
7. Order confirmation email
```

The service must:
- Convert ephemeral cart (session) to permanent order (database)
- Calculate all financial components accurately
- Validate delivery feasibility
- Ensure customer contact information is valid
- Generate unique tracking numbers
- Clear cart after successful order
- Handle both delivery and pickup modes

---

## 📖 Test Structure Overview

### Class: `OrderServiceTest`

This test class contains **17 test methods** covering:
- ✅ Order creation (delivery & pickup) - 2 tests
- ✅ Validation & error handling - 4 tests
- ✅ Phone number formats - 1 test (5 formats)
- ✅ Financial calculations - 1 test
- ✅ Business logic - 4 tests (cart clear, order numbers, items, client name)
- ✅ Order retrieval - 2 tests
- ✅ Status management - 2 tests
- ✅ Timestamps - 1 test

**Total Coverage**: 111 assertions across 17 tests

**Dependencies Mocked** (6 total):
1. `EntityManagerInterface` - Database persistence
2. `OrderRepository` - Order retrieval from database
3. `CartService` - Shopping cart data
4. `RestaurantSettingsService` - VAT rate, delivery fee configuration
5. `AddressValidationService` - Delivery address and radius validation
6. `RequestStack` - HTTP context (for sessions)

---

## 🔧 Test Setup and Configuration

### 1. `setUp()` Method - Complex Mock Environment

```php
protected function setUp(): void
{
    $this->entityManager = $this->createMock(EntityManagerInterface::class);
    $this->orderRepository = $this->createMock(OrderRepository::class);
    $this->cartService = $this->createMock(CartService::class);
    $this->restaurantSettings = $this->createMock(RestaurantSettingsService::class);
    $this->addressValidationService = $this->createMock(AddressValidationService::class);
    $this->requestStack = $this->createMock(RequestStack::class);
    
    // Configure defaults
    $this->restaurantSettings->method('getVatRate')->willReturn(0.10);
    $this->restaurantSettings->method('getDeliveryFee')->willReturn(5.00);
    
    $this->orderService = new OrderService(
        $this->entityManager,
        $this->orderRepository,
        $this->cartService,
        $this->requestStack,
        $this->restaurantSettings,
        $this->addressValidationService
    );
}
```

**Complexity Level**: **High** ⭐⭐⭐⭐⭐

OrderService has **6 dependencies** - the most of any service we've tested so far!

**Mock Strategy**:

1. **EntityManager**: Database operations (persist, flush)
   - Mocked because we don't want actual database writes in unit tests
   - Methods return void, so just verify they're called

2. **OrderRepository**: Finding existing orders
   - Mocked to return predetermined Order objects
   - Allows testing retrieval without database

3. **CartService**: Source of order data
   - Mocked to return test cart data
   - Must configure `getCart()` and `clear()` for each test

4. **RestaurantSettingsService**: Configuration values
   - VAT rate (10%)
   - Delivery fee (€5.00)
   - Configured in setUp() as these rarely change

5. **AddressValidationService**: Delivery validation
   - Returns validation results (valid/invalid, distance)
   - Must configure per test (delivery vs pickup)

6. **RequestStack**: HTTP context
   - Needed by other services but not directly used in tests
   - Mocked for completeness

**Why Tests Must Configure Mocks Explicitly**:

Unlike `TaxCalculationServiceTest` (1 dependency), OrderService has complex interactions. Tests must:
- Set up cart data
- Configure address validation (for delivery orders)
- Expect clear() to be called
- Not rely on defaults (makes tests more explicit and clear)

---

## 📋 Detailed Test Breakdown

### Test 1: `testCreateOrderWithDelivery()`

**Purpose**: Complete delivery order creation with all components

**Scenario**: Customer orders pasta for delivery with full information

**Complete Business Flow**:
```
1. Customer has cart: [Pasta ×2] = €31.00
2. Selects: Delivery mode
3. Enters: Address "123 Rue de la Paix, Marseille 13001"
4. Provides: Contact info (Jean Dupont, 06 12 34 56 78)
5. System validates address (within delivery radius)
6. System calculates:
   - Subtotal (HT): €28.18
   - Tax (10%): €2.82
   - Delivery: €5.00
   - Total: €36.00
7. Creates order in database
8. Clears cart
9. Returns order object
```

**Financial Calculation Breakdown**:
```
Cart Total (TTC):        €31.00
÷ 1.10                   
Subtotal (HT):          €28.18
Tax (TTC - HT):         €2.82
+ Delivery Fee:         €5.00
─────────────────────────────
Final Total:            €36.00
```

**What We Test**:

```php
// ARRANGE: Cart with items
$cartData = [
    'items' => [[
        'id' => 1,
        'name' => 'Pasta Carbonara',
        'price' => 15.50,
        'quantity' => 2
    ]],
    'total' => 31.00,  // 15.50 × 2
    'itemCount' => 2
];

$this->cartService->expects($this->once())
    ->method('getCart')
    ->willReturn($cartData);

// Address must be validated for delivery
$this->addressValidationService
    ->expects($this->once())
    ->method('validateAddressForDelivery')
    ->with('123 Rue de la Paix, Marseille', '13001')
    ->willReturn(['valid' => true, 'distance' => 2.5]);

// ACT: Create order
$order = $this->orderService->createOrder([
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
]);
```

**Key Validations** (28 assertions in this test!):
- ✅ Order number format: `ORD-YYYYMMDD-XXXX`
- ✅ Status: `PENDING`
- ✅ Delivery mode: `DELIVERY`
- ✅ Delivery details: address, zip, instructions, fee
- ✅ Payment mode: `CARD`
- ✅ Client info: first name, last name, phone, email
- ✅ Auto-generated full name: "Jean Dupont"
- ✅ Financial calculations: subtotal €28.18, tax €2.82, total €36.00
- ✅ Order items: 1 item with correct product details
- ✅ Cart cleared after creation

**Why So Many Assertions**:

This is an integration point for many business rules. We verify:
- Data transformation (cart → order)
- Calculations (tax, total)
- Validation (address, phone)
- Business logic (auto-generate name, clear cart)
- Entity relationships (order → order items)

---

### Test 2: `testCreateOrderWithTakeaway()`

**Purpose**: Pickup order (no delivery)

**Scenario**: Customer picks up order at restaurant

**Key Differences from Delivery**:
```
Delivery Order:
✅ Address required
✅ Address validated
✅ Delivery fee: €5.00
✅ May have delivery instructions

Pickup Order:
❌ No address
❌ No validation needed
❌ Delivery fee: €0.00
❌ No delivery instructions
```

**What We Test**:

```php
$orderData = [
    'deliveryMode' => 'pickup',  // Pickup, not delivery
    'paymentMode' => 'cash',
    'clientFirstName' => 'Marie',
    'clientLastName' => 'Martin',
    'clientPhone' => '0612345678',
    'clientEmail' => 'marie@example.com'
];

$order = $this->orderService->createOrder($orderData);

// ASSERT: Pickup mode
$this->assertEquals(DeliveryMode::PICKUP, $order->getDeliveryMode());

// ASSERT: No delivery details
$this->assertNull($order->getDeliveryAddress());
$this->assertNull($order->getDeliveryZip());

// ASSERT: Zero delivery fee
$this->assertEquals('0.00', $order->getDeliveryFee());

// ASSERT: Total equals subtotal (no delivery cost)
$this->assertEquals('15.50', $order->getTotal());
```

**Business Logic Verified**:
- ✅ Pickup mode allows order without address
- ✅ Delivery fee automatically set to €0.00
- ✅ Total calculation excludes delivery fee
- ✅ Address validation not triggered

---

### Test 3: `testCreateOrderFromEmptyCartThrowsException()`

**Purpose**: Cannot checkout with empty cart

**Scenario**: User tries to create order with no items

**Security & Data Integrity**:
```
Empty cart → Should NOT create order

Why?
- No revenue generated
- Wastes order numbers
- Database pollution
- Confuses reporting
- Opens door for spam/abuse
```

**What We Test**:

```php
// ARRANGE: Cart is empty
$this->cartService->expects($this->once())
    ->method('getCart')
    ->willReturn(['items' => [], 'total' => 0.00, 'itemCount' => 0]);

// ASSERT: Exception thrown
$this->expectException(\InvalidArgumentException::class);
$this->expectExceptionMessage('Le panier est vide');

// ACT: Try to create order (will fail)
$this->orderService->createOrder([...]);
```

**Real-World Scenarios**:
- User clears cart then refreshes old checkout page
- Browser back button issues
- Concurrent sessions (cart cleared in another tab)
- Direct API manipulation

---

### Test 4: `testCreateOrderDeliveryWithoutAddressThrowsException()`

**Purpose**: Delivery mode requires address

**Business Rule**:
```
deliveryMode = 'delivery' → deliveryAddress REQUIRED
deliveryMode = 'pickup' → deliveryAddress NOT REQUIRED
```

**What We Test**:

```php
// ACT: Try delivery without address
$this->orderService->createOrder([
    'deliveryMode' => 'delivery',  // Delivery selected
    // Missing: deliveryAddress ❌
]);

// ASSERT: Exception thrown
$this->expectException(\InvalidArgumentException::class);
$this->expectExceptionMessage("L'adresse de livraison est requise");
```

**Why This Check Matters**:
- Prevents creating undeliverable orders
- Saves courier time and fuel
- Avoids customer complaints
- Maintains data quality

---

### Test 5: `testCreateOrderWithInvalidAddressThrowsException()`

**Purpose**: Address must be within delivery radius

**Scenario**: Customer address is 15.5km away, but delivery radius is 10km

**Address Validation Process**:
```
1. Extract postal code from address
2. Get coordinates for postal code
3. Calculate distance from restaurant
4. Check if distance ≤ delivery radius

Example:
Restaurant: Marseille (13001)
Customer: Far City (99999) - 15.5km away
Delivery Radius: 10km
Result: ❌ Invalid (too far)
```

**What We Test**:

```php
// ARRANGE: Configure validation to fail
$this->addressValidationService
    ->expects($this->once())
    ->method('validateAddressForDelivery')
    ->with('Very Far Street, Another City', '99999')
    ->willReturn([
        'valid' => false,
        'error' => 'Livraison non disponible au-delà de 10km',
        'distance' => 15.5  // Too far!
    ]);

// ACT: Try to create order
$this->orderService->createOrder([
    'deliveryMode' => 'delivery',
    'deliveryAddress' => 'Very Far Street, Another City',
    'deliveryZip' => '99999'
]);

// ASSERT: Exception with validation error
$this->expectException(\InvalidArgumentException::class);
$this->expectExceptionMessage('Livraison non disponible au-delà de 10km');
```

**Business Impact**:
- Prevents accepting undeliverable orders
- Sets correct customer expectations
- Optimizes delivery operations
- Reduces failed deliveries

---

### Test 6: `testCreateOrderWithInvalidPhoneThrowsException()`

**Purpose**: Phone number must be valid French format

**Scenario**: Customer enters '123456' (too short, invalid)

**French Phone Number Rules**:
```
Valid National Formats:
- Mobile: 06 XX XX XX XX (starts with 06 or 07)
- Landline: 01-05 XX XX XX XX
- Total: 10 digits
- Must start with 0

Valid International Format:
- +33 X XX XX XX XX
- Total: 12 characters (+33 + 9 digits)

Invalid Examples:
- Too short: 123456
- Wrong prefix: 08 XX XX XX XX (premium rate)
- Wrong length: 06 123 456
- Letters: 06 AB CD EF GH
```

**What We Test**:

```php
$this->orderService->createOrder([
    'deliveryMode' => 'pickup',
    'clientPhone' => '123456',  // Invalid: too short
]);

// ASSERT: Exception thrown
$this->expectException(\InvalidArgumentException::class);
$this->expectExceptionMessage('Numéro de téléphone invalide');
```

**Why Validate Phone Numbers**:
- Required for order confirmation calls
- SMS notifications
- Delivery driver contact
- Customer service follow-up
- Marketing compliance (must be real numbers)

---

### Test 7: `testValidFrenchPhoneNumberFormats()`

**Purpose**: Accept all valid phone number formats

**Scenario**: Customers enter phone numbers in different formats

**Valid Formats Tested**:
```
1. With spaces:  06 12 34 56 78
2. No spaces:    0612345678
3. Landline:     01 23 45 67 89
4. With dashes:  06-12-34-56-78
5. With dots:    06.12.34.56.78
```

**What We Test**:

```php
$validPhones = [
    '06 12 34 56 78',
    '0612345678',
    '01 23 45 67 89',
    '06-12-34-56-78',
    '06.12.34.56.78',
];

foreach ($validPhones as $phone) {
    $order = $this->orderService->createOrder([
        'deliveryMode' => 'pickup',
        'clientPhone' => $phone,  // Each format tested
        ...
    ]);
    
    $this->assertInstanceOf(Order::class, $order);  // Success!
}
```

**UX Benefit**:

Users can enter phone numbers naturally:
- Copy/paste from contacts: "06 12 34 56 78"
- Type quickly without formatting: "0612345678"
- From business cards: "06-12-34-56-78"

All formats accepted! No frustrating "Invalid format" errors.

**Implementation Detail**:

```php
// Inside OrderService::validateFrenchPhoneNumber()
// Clean the number first
$cleanPhone = preg_replace('/[\s\-\.]/', '', $phone);
// Remove spaces, dashes, dots → '0612345678'

// Then validate cleaned number
if (strlen($cleanPhone) === 10 && str_starts_with($cleanPhone, '0')) {
    // Valid national format
}
```

---

### Test 8: `testOrderTotalCalculation()`

**Purpose**: Verify accurate financial calculations

**Scenario**: €50 cart + €5 delivery = €55 total

**Calculation Components**:
```
Cart Subtotal (TTC):     €50.00  ← Items with tax
÷ 1.10
Subtotal (HT):          €45.45  ← Base price (no tax)

Tax Calculation:
€50.00 - €45.45 =       €4.55   ← VAT amount

Final Total:
€50.00 + €5.00 =       €55.00  ← Customer pays
```

**What We Test**:

```php
// Cart total: €50.00
$cartData = [
    'items' => [['id' => 1, 'name' => 'Item', 'price' => 25.00, 'quantity' => 2]],
    'total' => 50.00,
    'itemCount' => 2
];

$order = $this->orderService->createOrder([
    'deliveryMode' => 'delivery',
    'deliveryAddress' => '123 Rue Test',
    'deliveryZip' => '13001',
    'deliveryFee' => '5.00',
    ...
]);

// ASSERT: Financial calculations
$this->assertEquals('45.45', $order->getSubtotal());  // €50 / 1.10
$this->assertEquals('4.55', $order->getTaxAmount());   // €50 - €45.45
$this->assertEquals('55.00', $order->getTotal());      // €50 + €5
$this->assertEquals('5.00', $order->getDeliveryFee());
```

**Critical for**:
- ✅ Tax reporting to government
- ✅ Financial reconciliation
- ✅ Accounting (revenue vs tax collected)
- ✅ Customer receipts
- ✅ Profit/loss calculation

---

### Test 9: `testCreateOrderClearsCart()`

**Purpose**: Cart must be cleared after successful order

**Why This Matters**:
```
Scenario 1 (Correct):
1. Create order from cart → SUCCESS
2. Cart cleared automatically
3. User sees "Order Confirmed" page
4. Cart badge shows 0 items
5. User can start new order

Scenario 2 (Wrong - without clear):
1. Create order from cart → SUCCESS
2. Cart NOT cleared ❌
3. User sees old items still in cart
4. User might accidentally order again
5. Duplicate orders = angry customers
```

**What We Test**:

```php
$this->cartService
    ->expects($this->once())  // MUST be called exactly once
    ->method('clear');

$order = $this->orderService->createOrder([...]);

// If clear() not called, test FAILS due to expects(once())
```

**Test Pattern**:

We use `expects($this->once())` to verify the method is called. If:
- Not called → Test fails
- Called multiple times → Test fails
- Called exactly once → Test passes ✅

---

### Test 10: `testOrderNumberFormat()`

**Purpose**: Unique, formatted order tracking numbers

**Order Number Format**:
```
ORD-YYYYMMDD-XXXX

Components:
- Prefix: ORD (identifies as order)
- Date: YYYYMMDD (e.g., 20251021)
- Random: XXXX (4 digits, 0001-9999)

Examples:
- ORD-20251021-0001
- ORD-20251021-5234
- ORD-20251022-0001 (next day)
```

**Benefits**:
- 📅 **Date visible**: Know when order was placed
- 🔢 **Sortable**: Chronological ordering easy
- 🔍 **Searchable**: Customers can reference easily
- 🎯 **Unique**: 9,999 orders per day possible
- 📞 **Human-readable**: Easy to say over phone

**What We Test**:

```php
// Create 2 orders
$order1 = $this->orderService->createOrder([...]);
$order2 = $this->orderService->createOrder([...]);

// ASSERT: Format matches regex
$this->assertMatchesRegularExpression('/^ORD-\d{8}-\d{4}$/', $order1->getNo());
$this->assertMatchesRegularExpression('/^ORD-\d{8}-\d{4}$/', $order2->getNo());

// ASSERT: Numbers are different (uniqueness)
$this->assertNotEquals($order1->getNo(), $order2->getNo());

// ASSERT: Contains today's date
$today = (new \DateTime())->format('Ymd');  // e.g., '20251021'
$this->assertStringContainsString($today, $order1->getNo());
```

**Uniqueness Verification**:

Creating 2 orders back-to-back should produce different numbers:
- Random component prevents collisions
- Even within same millisecond, numbers differ

---

### Test 11: `testOrderItemsCreatedFromCart()`

**Purpose**: Cart items converted to OrderItems

**Data Transformation**:
```
Cart Item (Session, Temporary):
{
    id: 1,
    name: 'Pasta Carbonara',
    price: 15.50,
    quantity: 2
}
    ↓ TRANSFORM ↓
OrderItem (Database, Permanent):
{
    productId: 1,
    productName: 'Pasta Carbonara',
    unitPrice: '15.50',
    quantity: 2,
    total: '31.00'
}
```

**Why This Transformation**:
- 🗄️ **Persistence**: Cart is session-based, order items are database records
- 📸 **Price Snapshot**: Capture current price (menu prices may change later)
- 📊 **Audit Trail**: Historical record of what was ordered at what price
- 🔍 **Reporting**: Can analyze popular items, revenue per item

**What We Test**:

```php
// Cart with 2 different items
$cartData = [
    'items' => [
        {id: 1, name: 'Pasta', price: 15.50, qty: 2},  // Item 1
        {id: 2, name: 'Tiramisu', price: 6.50, qty: 1}  // Item 2
    ],
    'total' => 37.50
];

$order = $this->orderService->createOrder([...]);

// ASSERT: 2 order items created
$items = $order->getItems();
$this->assertCount(2, $items);

// ASSERT: First item details
$item1 = $items[0];
$this->assertEquals(1, $item1->getProductId());
$this->assertEquals('Pasta', $item1->getProductName());
$this->assertEquals('15.5', $item1->getUnitPrice());
$this->assertEquals(2, $item1->getQuantity());
$this->assertEquals('31', $item1->getTotal());

// ASSERT: Second item details
$item2 = $items[1];
// ... similar assertions
```

**Entity Relationship**:
```
Order (1)
  ├── OrderItem (1) - Pasta ×2
  └── OrderItem (2) - Tiramisu ×1
```

Each OrderItem has `orderRef` pointing back to Order.

---

### Test 12: `testGetOrder()`

**Purpose**: Retrieve existing order by ID

**Use Cases**:
- 📄 View order details page
- 📧 Send confirmation email
- 📦 Prepare order in kitchen
- 🚚 Assign to delivery driver
- 🔍 Customer order tracking
- 👨‍💼 Admin panel management

**What We Test**:

```php
// ARRANGE: Mock order in repository
$mockOrder = $this->createMock(Order::class);
$mockOrder->method('getId')->willReturn(123);
$mockOrder->method('getNo')->willReturn('ORD-20251021-0001');

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
```

**Why `assertSame` vs `assertEquals`**:
- `assertEquals`: Values are equal (loose)
- `assertSame`: Same object instance (strict)

We want the exact same object returned from repository.

---

### Test 13: `testGetOrderNotFound()`

**Purpose**: Handle non-existent order gracefully

**Return null vs throw exception**:
```
Design Choice: Return null ✅

Alternatives:
1. Return null → Caller decides how to handle
2. Throw exception → Forces exception handling

Symfony Convention: Return null for "not found"
Allows flexible handling:
- Controller: if (!$order) { return 404; }
- API: if (!$order) { return JSON error; }
- Service: if (!$order) { use fallback; }
```

**What We Test**:

```php
// Repository returns null (not found)
$this->orderRepository
    ->expects($this->once())
    ->method('find')
    ->with(999)
    ->willReturn(null);

// ACT: Get order
$order = $this->orderService->getOrder(999);

// ASSERT: null returned (not exception)
$this->assertNull($order);
```

---

### Test 14: `testUpdateOrderStatus()`

**Purpose**: Change order status (workflow management)

**Order Status Flow**:
```
PENDING → Order placed, awaiting confirmation
    ↓
CONFIRMED → Restaurant accepted, preparing
    ↓
PREPARING → Being cooked/assembled
    ↓
DELIVERED → Completed successfully

CANCELLED ← Can cancel at any PENDING/CONFIRMED stage
```

**What We Test**:

```php
// ARRANGE: Mock order with PENDING status
$mockOrder = $this->createMock(Order::class);

// Expect setStatus called with CONFIRMED
$mockOrder
    ->expects($this->once())
    ->method('setStatus')
    ->with(OrderStatus::CONFIRMED);

// Repository returns order
$this->orderRepository
    ->method('find')
    ->with(123)
    ->willReturn($mockOrder);

// EntityManager should save changes
$this->entityManager
    ->expects($this->once())
    ->method('flush');

// ACT: Update status
$result = $this->orderService->updateOrderStatus(123, 'confirmed');

// ASSERT: Same order returned
$this->assertSame($mockOrder, $result);
```

**Business Uses**:
- Kitchen confirms order (PENDING → CONFIRMED)
- Chef starts preparation (CONFIRMED → PREPARING)
- Driver delivers (PREPARING → DELIVERED)
- Customer cancels (any → CANCELLED)

---

### Test 15: `testUpdateOrderStatusNotFoundThrowsException()`

**Purpose**: Cannot update non-existent order

**Scenario**: Try to update order ID 999 (doesn't exist)

**What We Test**:

```php
$this->orderRepository
    ->method('find')
    ->with(999)
    ->willReturn(null);  // Not found

$this->expectException(\InvalidArgumentException::class);
$this->expectExceptionMessage('Commande introuvable: 999');

$this->orderService->updateOrderStatus(999, 'confirmed');
```

**Error Handling**:

Unlike `getOrder()` which returns null, `updateOrderStatus()` throws exception because:
- Update implies order exists
- Caller expects successful update
- Null would be ambiguous (updated to null status?)
- Exception provides clear error message

---

### Test 16: `testClientNameAutoGenerated()`

**Purpose**: Full name generated from first + last name

**Auto-Generation Logic**:
```
Input:
clientFirstName: "Marie"
clientLastName: "Curie"

Auto-Generated:
clientName: "Marie Curie"  ← Concatenated automatically
```

**What We Test**:

```php
$order = $this->orderService->createOrder([
    'clientFirstName' => 'Marie',
    'clientLastName' => 'Curie',
    ...
]);

$this->assertEquals('Marie', $order->getClientFirstName());
$this->assertEquals('Curie', $order->getClientLastName());
$this->assertEquals('Marie Curie', $order->getClientName());  // Auto-generated!
```

**Benefits**:
- ✅ Data consistency
- ✅ Always have display name
- ✅ No duplicate data entry
- ✅ Easy to update (change first/last → name updates)

**Use Cases**:
- Display on order confirmation: "Thank you, Marie Curie!"
- Delivery label: "For: Marie Curie"
- Admin panel: Quick customer identification
- Email greeting: "Dear Marie Curie,"

---

### Test 17: `testOrderCreatedAtSet()`

**Purpose**: Timestamp records when order was placed

**Scenario**: Verify createdAt is set to current time

**Why Timestamps Matter**:
```
Business Uses:
- 📊 Report: "Orders placed today"
- ⏱️ SLA: "Delivery within 45 minutes"
- 📈 Analytics: "Peak hours: 12pm-2pm"
- 🔍 Debugging: "Order placed at 14:32:15"
- 💰 Accounting: "Revenue for October 2025"
```

**What We Test**:

```php
// Capture time before
$before = new \DateTimeImmutable();

// Create order
$order = $this->orderService->createOrder([...]);

// Capture time after
$after = new \DateTimeImmutable();

// ASSERT: createdAt is set
$this->assertInstanceOf(\DateTimeImmutable::class, $order->getCreatedAt());

// ASSERT: createdAt is between before and after
$this->assertGreaterThanOrEqual($before, $order->getCreatedAt());
$this->assertLessThanOrEqual($after, $order->getCreatedAt());
```

**Why `DateTimeImmutable`**:

```
DateTime (mutable):
$date1 = new DateTime('2025-10-21');
$date2 = $date1->modify('+1 day');  // $date1 changed! ❌

DateTimeImmutable (immutable):
$date1 = new DateTimeImmutable('2025-10-21');
$date2 = $date1->modify('+1 day');  // $date1 unchanged ✅
```

Immutable prevents accidental modifications - safer for timestamps!

---

## 🎨 Advanced Testing Patterns

### Pattern 1: Multiple Mocks Coordination

OrderService requires coordinating 6 mocks:

```php
// All must work together:
$cart = $this->cartService->getCart();                    // Mock 1
$taxRate = $this->restaurantSettings->getVatRate();       // Mock 2  
$validation = $this->addressValidationService->validate(); // Mock 3
$this->entityManager->persist($order);                     // Mock 4
$this->entityManager->flush();                             // Mock 5
$this->cartService->clear();                               // Mock 6

// Service orchestrates all these!
```

### Pattern 2: Conditional Mock Configuration

Different tests need different behaviors:

```php
// Delivery test: needs address validation
$this->addressValidationService
    ->expects($this->once())
    ->method('validateAddressForDelivery')
    ->willReturn(['valid' => true]);

// Pickup test: doesn't need address validation
// (mock not configured, method not called)
```

### Pattern 3: Exception Testing

```php
// Step 1: Declare expectation
$this->expectException(\InvalidArgumentException::class);
$this->expectExceptionMessage('Expected error message');

// Step 2: Execute code that throws
$this->service->methodThatThrows();

// If exception NOT thrown → Test FAILS
// If wrong exception type → Test FAILS
// If wrong message → Test FAILS
// If correct exception → Test PASSES ✅
```

### Pattern 4: Verification of Side Effects

```php
// Verify EntityManager methods called
$this->entityManager
    ->expects($this->once())
    ->method('persist')
    ->with($this->isInstanceOf(Order::class));

$this->entityManager
    ->expects($this->once())
    ->method('flush');

// These verify order is actually saved to database
// (even though database is mocked in tests)
```

---

## 🚀 How to Run These Tests

### Run All OrderService Tests
```bash
php bin/phpunit tests/Unit/Service/OrderServiceTest.php
```

### With Readable Output
```bash
php bin/phpunit --testdox tests/Unit/Service/OrderServiceTest.php
```

**Output**:
```
Order Service
 ✔ Create order with delivery
 ✔ Create order with takeaway
 ✔ Create order from empty cart throws exception
 ✔ Create order delivery without address throws exception
 ✔ Create order with invalid address throws exception
 ✔ Create order with invalid phone throws exception
 ✔ Valid french phone number formats
 ✔ Order total calculation
 ✔ Create order clears cart
 ✔ Order number format
 ✔ Order items created from cart
 ✔ Get order
 ✔ Get order not found
 ✔ Update order status
 ✔ Update order status not found throws exception
 ✔ Client name auto generated
 ✔ Order created at set

OK (17 tests, 111 assertions)
```

### Run Specific Test
```bash
php bin/phpunit --filter testCreateOrderWithDelivery tests/Unit/Service/OrderServiceTest.php
```

### All Service Tests
```bash
php bin/phpunit tests/Unit/Service/ --testdox
```

**Combined Output** (All Services):
```
Cart Service: 17 tests
Order Service: 17 tests
Tax Calculation Service: 11 tests
────────────────────────────────
Total: 45 tests, 216 assertions
Time: ~120ms
```

---

## 📈 What We Achieved

### Coverage Statistics
- **17 test methods** covering all critical functionality
- **111 assertions** validating multiple aspects
- **100% public method coverage** (3/3 public methods)
- **6 dependencies** properly mocked
- **Multiple scenarios**: delivery, pickup, validations, errors

### Quality Assurance
✅ **Order creation** validated (delivery & pickup)  
✅ **Financial calculations** accurate (subtotal, tax, total)  
✅ **Address validation** integrated  
✅ **Phone validation** comprehensive (5 formats)  
✅ **Error handling** tested (empty cart, invalid data)  
✅ **Business logic** verified (cart clear, auto-generated names)  
✅ **Order tracking** working (unique numbers, timestamps)  
✅ **Status management** operational  

---

## 🎓 Key Concepts Learned

### 1. Complex Service Mocking

Managing 6 dependencies in one service:

```php
new OrderService(
    $entityManager,      // 1. Database
    $orderRepository,    // 2. Retrieval
    $cartService,        // 3. Cart data
    $requestStack,       // 4. HTTP
    $restaurantSettings, // 5. Config
    $addressValidation   // 6. Validation
);
```

All mocked for true unit testing!

### 2. Conditional Logic Testing

```php
if ($deliveryMode === DeliveryMode::DELIVERY) {
    // Validate address
    // Set delivery fee
} else {
    // No validation
    // Zero delivery fee
}
```

Need tests for BOTH paths!

### 3. Financial Calculation Testing

```php
// Cart total includes tax (TTC)
$subtotalTTC = $cart['total'];  // €50.00

// Convert to without tax (HT)
$subtotalHT = $subtotalTTC / (1 + $taxRate);  // €45.45

// Calculate tax amount
$taxAmount = $subtotalTTC - $subtotalHT;  // €4.55

// Add delivery
$total = $subtotalTTC + $deliveryFee;  // €55.00
```

Must verify all intermediate steps!

### 4. Regex Pattern Matching

```php
// Order number format: ORD-20251021-0001
$this->assertMatchesRegularExpression('/^ORD-\d{8}-\d{4}$/', $orderNo);

Pattern breakdown:
^        - Start of string
ORD-     - Literal "ORD-"
\d{8}    - Exactly 8 digits (date)
-        - Literal dash
\d{4}    - Exactly 4 digits (random)
$        - End of string
```

### 5. Loop Testing

```php
foreach ($validPhones as $phone) {
    $order = $this->service->createOrder(['phone' => $phone]);
    $this->assertInstanceOf(Order::class, $order);
}
```

Test multiple values in single test method!

---

## 🔍 Why These Are True Unit Tests

### ✅ Isolation
- **No real database**: EntityManager mocked
- **No real sessions**: RequestStack mocked
- **No real cart**: CartService mocked
- **No real validation**: AddressValidationService mocked
- **No external APIs**: Everything internal

### ✅ Speed
```
17 tests in 68ms = 4ms per test
```
Exceptionally fast despite testing complex service!

### ✅ Independence
- Each test has fresh mocks (setUp runs before each)
- No shared state
- Can run in any order
- Can run in parallel

### ✅ Repeatability
Same inputs always produce same outputs:
```php
createOrder($data) → Always returns same Order structure
updateOrderStatus(123, 'confirmed') → Always updates status
```

---

## 📊 Test Results Interpretation

### ✅ All Tests Pass
```
.................                                                 17 / 17 (100%)
OK (17 tests, 111 assertions)
```
- `.` = successful test
- `17 / 17` = all passed
- `111 assertions` = comprehensive validation

### ❌ Test Failure Example
```
F................                                                 17 / 17 (100%)

1) OrderServiceTest::testCreateOrderWithDelivery
Failed asserting that two strings are equal.
--- Expected
+++ Actual
@@ @@
-'28.18'
+'28.17'
```

Shows expected vs actual, making debugging easy!

---

## 🔗 Related Files

| File | Purpose |
|------|---------|
| `OrderServiceTest.php` | The test file (this file documents) |
| `src/Service/OrderService.php` | Service being tested |
| `src/Entity/Order.php` | Order entity |
| `src/Entity/OrderItem.php` | Order item entity |
| `src/Enum/OrderStatus.php` | Order statuses |
| `src/Enum/DeliveryMode.php` | Delivery/pickup modes |
| `src/Enum/PaymentMode.php` | Payment methods |

---

## ⏭️ Next Steps

After mastering OrderService tests:

1. ✅ **Understand** complex service testing
2. ✅ **Practice** coordinating multiple mocks
3. ✅ **Apply** patterns to AddressValidationService
4. ✅ **Write** tests for TableAvailabilityService
5. ✅ **Graduate** to Integration tests

---

## ❓ FAQ

**Q: Why 6 dependencies? Isn't that too many?**  
A: OrderService is a coordinator/orchestrator. It's normal for such services to have many dependencies. We mock them all to keep tests fast and isolated.

**Q: Why test phone validation if it's a private method?**  
A: We test it indirectly through `createOrder()`. If phone validation fails, order creation fails - which our tests verify.

**Q: Why is cart cleared after order creation?**  
A: Prevents duplicate orders and gives user clean slate for next order.

**Q: Why does testCreateOrderWithDelivery have 28 assertions?**  
A: It's the main happy path test - validates entire order creation flow with all components.

**Q: What's the difference between delivery and pickup orders?**  
A: Delivery requires address validation and has delivery fee. Pickup requires neither.

**Q: Why test order number uniqueness?**  
A: Order numbers are used for tracking. Duplicates would cause confusion and system errors.

---

**Created**: October 21, 2025  
**Status**: ✅ All tests passing  
**PHPUnit Version**: 11.5.39  
**PHP Version**: 8.2.26  
**Test Execution Time**: ~68ms  
**Complexity**: High (6 dependencies)  
**Author**: Le Trois Quarts Development Team

---

🎉 **Excellent Work! OrderService is fully tested!**

