# 📚 OrderServiceIntegrationTest – Detailed Overview

## 🎯 Purpose
`OrderServiceIntegrationTest` verifies that the real `OrderService` works correctly **end-to-end** when wired with the Symfony service container, Doctrine ORM and a live (in-memory) database. Unlike the unit tests that mock every collaborator, these integration tests ensure that:

- the Doctrine mappings are correct (entities persist and relations are stored),
- transactions, coupon logic and cart clearing happen exactly as in production,
- the service still behaves when the environment is bootstrapped by the Symfony kernel.

Two realistic business scenarios are covered:
1. ✅ *Happy path* – an order is created from a populated cart, a coupon is applied, totals are recalculated and the cart is cleared.
2. ✅ *Error path* – attempting to checkout with an empty cart fails with a user-friendly exception.

---

## 🧪 Test Class Summary
- **File**: `tests/Integration/Service/OrderServiceIntegrationTest.php`
- **Tests**: 2 methods, 14 assertions
- **Execution time**: ~80 ms (PDO SQLite in-memory)
- **Dependencies loaded from the container**:
  - `EntityManagerInterface`
  - `MenuItemRepository`
  - `MenuItemImageResolver`
  - `RestaurantSettingsService`
  - `ParameterBagInterface`
- **Stubs**:
  - `AddressValidationService` is replaced with an inline stub that always accepts the address (no external HTTP call during tests).

---

## ⚙️ Environment Setup (performed in `setUp()`)
1. **Force sqlite:///:memory:** by overriding `DATABASE_URL` before the kernel boots.
2. **Boot the Symfony kernel** and fetch the DI container.
3. **Reset Doctrine schema** via `SchemaTool` to guarantee a pristine database for each test case.
4. **Craft a fake session** (using `MockArraySessionStorage`) and push it onto a `RequestStack`. This allows the real `CartService` to store session data exactly as in production.
5. **Instantiate `CartService`** with the actual repository and `MenuItemImageResolver` fetched from the container.

This setup ensures the service operates in conditions almost identical to the real application.

---

## 🔍 Scenario Breakdown
### 1. `testCreateOrderWithCouponPersistsOrderAndClearsCart()`
**Business goal**: confirm that a complete checkout flow works from cart → order → coupon → totals → cart clear.

#### Arrange
- Persist a real `Coupon` entity (10% discount, currently active).
- Prime the synthetic session with a cart containing two dishes at €15.00 each.
- Build an `OrderCreateRequest` DTO referencing the coupon.
- Instantiate the real `OrderService` with our container-provided dependencies.

#### Act
Call `createOrder($dto)`.

#### Assert (highlights):
- Order is persisted (`id` not null) and has a number matching `ORD-YYYYMMDD-XXXX`.
- Status is `OrderStatus::PENDING`, delivery mode is `pickup`, payment mode is `card`.
- Totals reflect coupon application (`27.27` subtotal, `2.73` VAT, `27.00` grand total, `3.00` discount).
- Exactly one `OrderItem` was materialised.
- Cart is cleared (`itemCount` becomes 0, no leftover items).

### 2. `testCreateOrderThrowsExceptionWhenCartIsEmpty()`
**Business goal**: protect against empty checkouts.

#### Arrange
- Ensure the cart is emptied via `CartService::clear()`.
- Prepare a minimal DTO.

#### Act & Assert
- Expect an `InvalidArgumentException` with message “Le panier est vide”.
- Verify that no order is created.

---

## 📦 Helper: `createOrderService()`
The helper method reuses the real `RestaurantSettingsService`, entity repositories and connection from the container, but supplies a tiny anonymous class stub for `AddressValidationService` so the tests remain deterministic (no outbound HTTP to OpenStreetMap). This keeps the focus on persistence logic without sacrificing performance.

---

## ▶️ Running the Tests
```bash
php bin/phpunit tests/Integration/Service/OrderServiceIntegrationTest.php
```
With TestDox output:
```bash
php bin/phpunit --testdox tests/Integration/Service/OrderServiceIntegrationTest.php
```
Expected summary:
```
Order Service Integration
 ✔ Create order with coupon persists order and clears cart
 ✔ Create order throws exception when cart is empty
```

---

## ✅ Key Takeaways
- These integration tests ensure that Doctrine mappings, transaction handling and coupon logic do not regress.
- Running against an in-memory database keeps feedback fast while providing high confidence.
- The tests operate on the real `CartService`, meaning the entire checkout flow is exercised from “cart in session” through to “order persisted”.
- Errors are validated just like in production – trying to checkout an empty cart triggers the exact exception the API would return.

---

📌 *Updated: October 21, 2025 – Symfony 6.x / PHPUnit 11.5.39 / PHP 8.2.26.*
