# 📚 ReviewServiceIntegrationTest – Detailed Overview

## 🎯 Purpose
The `ReviewServiceIntegrationTest` ensures that real `ReviewService` operations succeed when running inside the Symfony runtime with Doctrine connected to an in-memory database. It confirms that a validated review DTO is transformed into an actual `Review` entity persisted to the database with the correct default moderation status.

---

## 🧪 Test Class Summary
- **File**: `tests/Integration/Service/ReviewServiceIntegrationTest.php`
- **Tests**: 1 method, 7 assertions
- **Focus**: creation workflow for general restaurant reviews (no dish association)
- **Environment**:
  - Symfony kernel booted (container available)
  - Doctrine ORM schema reset before each test
  - SQLite in-memory database (fast, isolated)

---

## ⚙️ Test Setup Highlights
1. **Override `DATABASE_URL`** to `sqlite:///:memory:` so the kernel connects to an ephemeral test database.
2. **Boot the kernel** via `KernelTestCase::bootKernel()` and fetch the `EntityManagerInterface` from the container.
3. **Reset the schema** on every run using `SchemaTool` to avoid leftover data.
4. **Tear down** closes the EntityManager and shuts the kernel down cleanly.

This setup mimics how the service runs in production, minus external dependencies.

---

## 🔍 Scenario
### `testCreateReviewPersistsEntityWithModerationDisabled()`
**Goal**: Verify that a new review submitted through the API is persisted with `isApproved = false` so moderators can approve it later.

#### Arrange
- Build a `ReviewCreateRequest` DTO with test data (name, email, rating, comment).
- Instantiate the real `ReviewService` using the entity manager.

#### Act
- Call `createReview($dto)`.

#### Assert
- The returned entity has a generated ID and retains the DTO values.
- The moderation flag defaults to `false` (requires approval from admins).
- Fetching all reviews from the repository returns exactly one record, proving data hit the database.

---

## ▶️ Running the Test
```bash
php bin/phpunit tests/Integration/Service/ReviewServiceIntegrationTest.php
```
With TestDox:
```bash
php bin/phpunit --testdox tests/Integration/Service/ReviewServiceIntegrationTest.php
```
Output:
```
Review Service Integration
 ✔ Create review persists entity with moderation disabled
```

---

## ✅ Key Takeaways
- Validates Doctrine mappings and default values (createdAt, isApproved) without mocks.
- Uses a real database connection, giving confidence that production persistence logic is intact.
- Provides a safety net for later refactors of `ReviewService` or the `Review` entity.

---

📌 *Updated: October 21, 2025 – Symfony 6.x / PHPUnit 11.5.39 / PHP 8.2.26.*
