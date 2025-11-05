# Database setup, entities, migrations, and usage

This document explains how the database is configured and created via Doctrine ORM entities and migrations, how it is used throughout the project, and what relations exist between tables (and why).

## 1) Setup and creation flow

1. Configure Doctrine
   - Config file: `config/packages/doctrine.yaml`
   - Connection string via `.env` → `DATABASE_URL`
2. Generate entities (schema-first via code)
   - `php bin/console make:entity` (fields, relations)
3. Generate migrations
   - `php bin/console make:migration`
   - Produces versioned PHP classes in `migrations/`
4. Apply migrations (create/update schema)
   - `php bin/console doctrine:migrations:migrate`
5. Seed initial data (optional)
   - Fixtures under `src/DataFixtures/` (e.g., allergens, menu enrichment, reviews)
6. Keep schema in sync
   - Any entity change → new migration → migrate.

We use code‑first design: entities are the single source of truth; migrations are reproducible change sets.

## 2) Entities and relations (tables and why they exist)

Below is a high‑level overview of core entities, their key fields, and relations. The rationales explain why relations are designed this way.

### Order (`src/Entity/Order.php`)
- Purpose: Aggregate root for a customer order.
- Key fields: `status` (enum), `deliveryMode` (enum), `paymentMode` (enum), `subtotal`, `taxAmount`, `total`, client info, `createdAt`.
- Relations:
  - OneToMany `items` → `OrderItem` (owning side is `OrderItem.orderRef`). Rationale: a single order contains multiple line items.
  - ManyToOne `coupon` → `Coupon` (nullable, on delete set null). Rationale: optional discount per order.

### OrderItem (`src/Entity/OrderItem.php`)
- Purpose: Line item in an order.
- Key fields: denormalized `productId`, `productName`, `unitPrice`, `quantity`, `total` (for resilience/reporting).
- Relations:
  - ManyToOne `orderRef` → `Order` (required, cascade persist). Rationale: each item belongs to exactly one order.
  - ManyToOne `menuItem` → `MenuItem` (nullable, on delete set null). Rationale: keep link to catalog item when available, but allow historical orders to survive catalog changes.

### MenuItem (`src/Entity/MenuItem.php`)
- Purpose: Menu/catalog item displayed to customers.
- Typical relations (inferred from usage):
  - ManyToMany `tags` → `Tag` (filtering/tech labels).
  - ManyToMany `badges` → `Badge` (marketing/UX badges).
  - ManyToMany `allergens` → `Allergen` (food safety info).

### Review (`src/Entity/Review.php`)
- Purpose: Customer reviews for dishes or the restaurant overall.
- Key fields: `rating`, `comment`, `isApproved`, `createdAt`.
- Relations:
  - ManyToOne `menuItem` → `MenuItem` (nullable, on delete set null). Rationale: when null, review is about the restaurant in general; otherwise about a specific dish.

### GalleryImage (`src/Entity/GalleryImage.php`)
- Purpose: Images used in gallery pages.
- Fields: `title`, `description`, `imagePath`, `category`, `displayOrder`, `isActive`, timestamps.

### Coupon (`src/Entity/Coupon.php`)
- Purpose: Promotional discounts.
- Relation: orders may link to a coupon (see `Order.coupon`).

### Reservation (`src/Entity/Reservation.php`) and Table (`src/Entity/Table.php`)
- Purpose: Reservations and table layout/capacity (used by availability service).

### User (`src/Entity/User.php`)
- Purpose: Authentication and authorization for back‑office/admin.

### Supporting entities
- `Tag`, `Badge`, `Allergen`, `Drink`, `NutritionFacts`, `ContactMessage` — domain features used across the site.

## 3) How the database is used in the project

### Repositories (query access)
- Standard Doctrine repositories under `src/Repository/` provide query methods, e.g.:
  - `ReviewRepository`: listing/aggregates of approved reviews.
  - `MenuItemRepository`: optimized lightweight selectors for related dishes.
  - `GalleryImageRepository`: active images, counts by category.
  - `OrderRepository`, `CouponRepository`, etc.

### Services and controllers
- Controllers call services which orchestrate work and persist entities.
- Example: `OrderService::createOrder()`
  - Reads the cart (API), validates delivery, sets fees, applies pricing math, persists `Order` and `OrderItem` rows.
  - Uses Strategy + Factory for delivery and pricing (behavior unchanged, improved extensibility).
- Example: `OrderController` provides `/api/order` endpoints; DTOs map entities to API shape.

### Templates (Twig)
- Pages render data from controllers: menu, dish detail (badges/allergens), gallery, reviews, etc.

## 4) Relations and their rationale

- Order — OrderItem: OneToMany. Natural aggregate; cascade persist ensures items save with the order.
- Order — Coupon: ManyToOne (nullable). A single coupon may be used by many orders; an order uses at most one coupon.
- OrderItem — MenuItem: ManyToOne (nullable, set null). Keeps historical linkage, while allowing catalog changes.
- MenuItem — Tag/Badge/Allergen: ManyToMany. A dish belongs to multiple labels; labels apply to many dishes.
- Review — MenuItem: ManyToOne (nullable). Supports both dish‑specific and global reviews.

## 5) Migrations management

- All migrations live in `migrations/` and are applied in order.
- Example (performance only, non‑destructive): `migrations/Version20251105120000.php` adds indexes for queries on reviews (`menu_item_id`, `is_approved`, `created_at`), orders (`status`, `created_at`), and gallery images (`is_active`, `category`, `created_at`).
- Typical developer flow:
  1. Change entity mapping (attributes).
  2. `make:migration` to generate delta.
  3. `migrate` to apply to DB.

## 6) Querying patterns and performance

- Use ORM QueryBuilder for most queries; scalar/partial selects for lightweight lists where hydration isn’t needed (e.g., related dishes card data).
- Aggregate queries use `COUNT/AVG` via repository helpers (`ReviewRepository::getApprovedStatsForMenuItem`).
- Indexes added for high‑cardinality filters (status, approval, createdAt, foreign keys), improving dashboard widgets and dish pages.
- Safe caching can be added on top at the repository or controller level if needed.

## 7) Transactions and data integrity

- `OrderService` persists the order and its items in a single unit of work. Doctrine’s transactional write (single flush) ensures consistency.
- Defensive denormalization in `OrderItem` (product id/name/price) makes historical orders resilient to catalog changes.

## 8) Fixtures and test data

- `src/DataFixtures/*` populate allergens, menu enrichment, and reviews for local/dev environments.
- Run via `php bin/console doctrine:fixtures:load` (if the project enables DoctrineFixturesBundle).

## 9) How to evolve safely

- Add fields/relations at the entity level; generate & run a migration; seed as needed.
- For read performance: prefer repository methods with `getArrayResult()` when full entities are not required.
- For write behavior changes (e.g., new delivery modes/pricing): implement a new strategy class, no schema change required.

## 10) Rollback strategy

- Doctrine migrations are reversible when `down()` is implemented.
- In emergencies: revert to a previous migration version (`doctrine:migrations:migrate prev`) and redeploy.

## 11) How SQL is executed (ORM/DBAL vs PDO)

This project does not use raw PHP PDO directly. Instead, it uses Doctrine ORM and Doctrine DBAL, which themselves sit on top of PDO. That means:

- The physical DB connection is a PDO connection managed by Doctrine.
- ORM operations are translated into SQL and executed via the underlying DBAL → PDO.
- All parameters are bound safely (prepared statements), protecting against SQL injection.

### Patterns used

- ORM QueryBuilder for entity queries (hydrated objects):
```php
$reviews = $this->createQueryBuilder('r')
    ->andWhere('r.isApproved = :approved')
    ->setParameter('approved', true)
    ->orderBy('r.createdAt', 'DESC')
    ->getQuery()
    ->getResult();
```

- Scalar/array results when hydration is not needed (lighter, still parameterized):
```php
$rows = $this->createQueryBuilder('m')
    ->select('m.id, m.name, m.description, m.price, m.image')
    ->andWhere('m.category = :category')
    ->setParameter('category', $category)
    ->setMaxResults(3)
    ->getQuery()
    ->getArrayResult();
```

- Aggregates via ORM (still prepared under the hood):
```php
$row = $this->createQueryBuilder('r')
    ->select('COUNT(r.id) AS cnt, COALESCE(AVG(r.rating), 0) AS avg')
    ->andWhere('r.menuItem = :id')
    ->setParameter('id', $menuItemId)
    ->getQuery()
    ->getSingleResult();
```

### Optional: DBAL for raw SQL (recommended over bare PDO)

If/when we need explicit SQL, we use Doctrine DBAL, which reuses the same PDO connection and transaction context:

```php
// $entityManager is injected; reuse its connection
$conn = $entityManager->getConnection();

$sql = 'SELECT id, name FROM menu_item WHERE category = :category LIMIT :n';
$result = $conn->executeQuery($sql, [
    'category' => $category,
    'n' => 10,
], [
    'category' => \PDO::PARAM_STR,
    'n' => \PDO::PARAM_INT,
]);

$rows = $result->fetchAllAssociative();
```

Advantages of DBAL vs direct PDO:
- shared connection/pool/transactions with the rest of the app;
- consistent error handling and logging;
- automatic parameter binding helpers; portable types.

When to use DBAL:
- bulk operations or vendor‑specific SQL features;
- complex reports that are hard to express with ORM;
- performance‑critical read‑only queries where hydration is unnecessary.

