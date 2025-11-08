# 📚 ReservationServiceIntegrationTest – Detailed Overview

## 🎯 Purpose
`ReservationServiceIntegrationTest` validates that `ReservationService` persists reservations correctly when executed inside the full Symfony + Doctrine environment. The test covers the end-to-end flow of taking a validated reservation request DTO and converting it into a stored `Reservation` entity with the expected defaults (status, confirmation flags, timestamps).

---

## 🧪 Test Class Summary
- **File**: `tests/Integration/Service/ReservationServiceIntegrationTest.php`
- **Tests**: 1 method, 8 assertions
- **Focus**: creation workflow for new reservations in “pending” state
- **Runtime**: Symfony kernel + Doctrine ORM using in-memory SQLite for speed and isolation

---

## ⚙️ Environment Setup
1. **Override `DATABASE_URL`** with `sqlite:///:memory:` ensuring the entity manager talks to an ephemeral database.
2. **Boot the kernel** using `KernelTestCase` – full container access just like production.
3. **Reset schema** each run via Doctrine’s `SchemaTool` to guarantee fresh tables.
4. **Tear down** closes the entity manager and shuts down the kernel to avoid cross-test leaks.

---

## 🔍 Scenario
### `testCreateReservationInitializesPendingReservation()`
**Business goal**: confirm that creating a reservation from API data sets the expected defaults and stores the record.

#### Arrange
- Populate a `ReservationCreateRequest` DTO (name, contact details, date/time, guest count, optional message).
- Instantiate the real `ReservationService` with the entity manager.

#### Act
- Call `createReservation($dto)`.

#### Assert
- Entity receives an ID (persisted).
- Fields mirror DTO values (first name, last name, email, message, etc.).
- Initial status is `ReservationStatus::PENDING` and `isConfirmed` flag is `false`.
- Repository lookup returns exactly one reservation confirming database persistence.

---

## ▶️ Running the Test
```bash
php bin/phpunit tests/Integration/Service/ReservationServiceIntegrationTest.php
```
With TestDox output:
```bash
php bin/phpunit --testdox tests/Integration/Service/ReservationServiceIntegrationTest.php
```
Expected output:
```
Reservation Service Integration
 ✔ Create reservation initializes pending reservation
```

---

## ✅ Key Takeaways
- Ensures Doctrine mappings + defaults remain correct after refactors.
- Gives confidence that reservation requests will be persisted properly when the API is exercised.
- Acts as a high-level safeguard on top of any unit tests targeting reservation logic.

---

📌 *Updated: October 21, 2025 – Symfony 6.x / PHPUnit 11.5.39 / PHP 8.2.26.*
