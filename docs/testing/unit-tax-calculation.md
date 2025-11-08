# 📚 TaxCalculationServiceTest - Detailed Explanation

## 🎯 What is Being Tested?

`TaxCalculationService` is a critical service that handles all VAT (Value Added Tax) calculations for the restaurant. In France, different types of food services have different VAT rates:
- **10%** - Restaurant dine-in service (food consumed on premises)
- **5.5%** - Takeaway food (food consumed off premises)
- **20%** - Standard rate (alcoholic beverages, non-food items)

This service is essential for:
- ✅ **Financial accuracy** - Ensuring correct tax calculations for all transactions
- ✅ **Tax compliance** - Meeting French tax authority requirements
- ✅ **Accounting** - Proper breakdown of revenue vs. tax collected
- ✅ **Reporting** - Generating accurate financial reports

## 📖 Test Structure Overview

### Class: `TaxCalculationServiceTest`

This test class contains **11 test methods** covering:
- ✅ Core functionality (TTC ↔ HT conversions)
- ✅ Edge cases (zero amounts, large values)
- ✅ Precision validation (rounding, decimal handling)
- ✅ Flexibility (multiple tax rates)
- ✅ Type safety (return value validation)
- ✅ Real-world scenarios (actual order calculations)

**Total Coverage**: 43 assertions across 11 tests

---

## 🔧 Test Setup and Configuration

### 1. `setUp()` Method - Test Environment Initialization

```php
protected function setUp(): void
{
    $this->restaurantSettings = $this->createMock(RestaurantSettingsService::class);
    $this->restaurantSettings->method('getVatRate')->willReturn(0.10);
    $this->taxCalculationService = new TaxCalculationService($this->restaurantSettings);
}
```

**What Happens Here:**
1. **Mock Creation**: Creates a simulated `RestaurantSettingsService`
2. **Mock Configuration**: Sets the mock to return 10% VAT rate
3. **Service Instantiation**: Creates the actual service we're testing

**Why Use Mocks?**
- ⚡ **Speed**: No database queries or file I/O
- 🎯 **Isolation**: Tests only the TaxCalculationService, not dependencies
- 🔄 **Control**: Predictable behavior for consistent test results
- 📦 **Independence**: No need for configuration files or database setup

**Real-World Analogy:**
Think of it like testing a calculator. You don't need to test where the calculator gets its power from (battery), you just need to verify the calculations are correct. The mock "battery" (RestaurantSettingsService) provides predictable power (VAT rate).

---

## 📋 Detailed Test Breakdown

### Test 1: `testCalculateTaxFromTTC()`
**Purpose**: Calculate tax breakdown from price including tax (TTC → HT)

**Scenario**: Menu item priced at €110 (including 10% VAT)

**Mathematical Formula**:
```
HT (Base Price) = TTC / (1 + VAT_RATE)
€100 = €110 / (1 + 0.10)
€100 = €110 / 1.10

Tax Amount = TTC - HT
€10 = €110 - €100
```

**What We Test**:
```php
$amountWithTax = 110.0;
$result = $this->taxCalculationService->calculateTaxFromTTC($amountWithTax);

// Verify structure
$this->assertIsArray($result);
$this->assertArrayHasKey('amountWithoutTax', $result);

// Verify calculations
$this->assertEquals(100.0, $result['amountWithoutTax']);  // Base price
$this->assertEquals(10.0, $result['taxAmount']);          // Tax amount
$this->assertEquals(110.0, $result['amountWithTax']);     // Total (unchanged)
$this->assertEquals(0.10, $result['taxRate']);            // Rate used
```

**Real-World Usage**:
When a customer sees "Pasta €110" on the menu, the restaurant needs to know:
- Base revenue: €100 (what they actually earn)
- Tax collected: €10 (what they must remit to government)

---

### Test 2: `testCalculateTaxFromHT()`
**Purpose**: Calculate final price from base price (HT → TTC)

**Scenario**: Wholesale price of €100 (excluding VAT)

**Mathematical Formula**:
```
Tax Amount = HT × VAT_RATE
€10 = €100 × 0.10

TTC (Final Price) = HT + Tax Amount
€110 = €100 + €10

Or simplified: TTC = HT × (1 + VAT_RATE)
€110 = €100 × 1.10
```

**What We Test**:
```php
$amountWithoutTax = 100.0;
$result = $this->taxCalculationService->calculateTaxFromHT($amountWithoutTax);

$this->assertEquals(100.0, $result['amountWithoutTax']);  // Input (unchanged)
$this->assertEquals(10.0, $result['taxAmount']);          // Calculated tax
$this->assertEquals(110.0, $result['amountWithTax']);     // Final customer price
```

**Real-World Usage**:
- Setting menu prices from supplier costs
- Calculating selling price with proper margin
- B2B invoicing where base price is separate from tax

---

### Test 3: `testCalculateTaxFromTTCWithRounding()`
**Purpose**: Validate proper decimal rounding

**Scenario**: Menu item priced at €15.99 (including VAT)

**Why This Matters**:
- 💰 **Legal Requirement**: Currency must have exactly 2 decimal places
- 📊 **Financial Accuracy**: Rounding errors accumulate over many transactions
- 🔍 **Audit Trail**: Tax authorities require precision
- 💻 **Floating-Point Issues**: Computers don't handle decimals perfectly

**Mathematical Breakdown**:
```
€15.99 / 1.10 = €14.536363636... (repeating decimal)
Rounded to 2 decimals: €14.54 (base price)
Tax: €15.99 - €14.54 = €1.45
```

**What We Test**:
```php
$amountWithTax = 15.99;
$result = $this->taxCalculationService->calculateTaxFromTTC($amountWithTax);

// Note the delta parameter (0.01) for floating-point tolerance
$this->assertEquals(14.54, $result['amountWithoutTax'], '', 0.01);
$this->assertEquals(1.45, $result['taxAmount'], '', 0.01);
```

**The Delta Parameter Explained**:
Due to how computers store floating-point numbers, `14.54` might actually be stored as `14.5399999999` or `14.5400000001`. The delta of `0.01` says "as long as the value is within €0.01 of the expected value, that's acceptable." This is standard practice for financial calculations.

---

### Test 4 & 5: `testCalculateTaxFromTTCWithZeroAmount()` & `testCalculateTaxFromHTWithZeroAmount()`
**Purpose**: Handle edge case of zero amounts

**Scenario**: Empty cart or free items (€0)

**Why Test This**:
- 🛡️ **Prevent Crashes**: Ensure no division by zero errors
- 🎁 **Free Items**: Handle promotional items correctly
- 🧪 **Boundary Testing**: Always test edge cases

**What We Test**:
```php
$amountWithTax = 0.0;
$result = $this->taxCalculationService->calculateTaxFromTTC($amountWithTax);

// All values should be zero
$this->assertEquals(0.0, $result['amountWithoutTax']);
$this->assertEquals(0.0, $result['taxAmount']);
$this->assertEquals(0.0, $result['amountWithTax']);
```

**Mathematical Logic**:
```
€0 / 1.10 = €0
€0 × 0.10 = €0
€0 + €0 = €0
```

---

### Test 6: `testCalculateTaxWithDifferentTaxRates()`
**Purpose**: Validate flexibility with multiple VAT rates

**Scenario**: Test with 5.5%, 10%, and 20% VAT rates

**French Tax Context**:
```
Dine-in restaurant service:    10% VAT
Takeaway food:                  5.5% VAT
Alcoholic beverages:            20% VAT
Standard goods:                 20% VAT
```

**Why This Matters**:
Restaurants often serve items with different tax rates in the same transaction. For example:
- Main course (dine-in): 10%
- Bottle of wine: 20%
- Dessert to-go: 5.5%

**What We Test**:
```php
// Test with 5.5% VAT
$restaurantSettings->method('getVatRate')->willReturn(0.055);
$result = $taxService->calculateTaxFromTTC(105.5);
// €105.50 / 1.055 = €100 (base) + €5.50 (tax)

// Test with 20% VAT
$restaurantSettings->method('getVatRate')->willReturn(0.20);
$result = $taxService->calculateTaxFromTTC(120.0);
// €120 / 1.20 = €100 (base) + €20 (tax)
```

---

### Test 7: `testGetTaxRate()`
**Purpose**: Verify tax rate retrieval

**Scenario**: Get the currently configured VAT rate

**What We Test**:
```php
$taxRate = $this->taxCalculationService->getTaxRate();

$this->assertEquals(0.10, $taxRate);        // Correct value
$this->assertIsFloat($taxRate);             // Correct type
```

**Use Cases**:
- Displaying tax information on receipts
- Generating tax reports
- Configuration validation
- API responses

---

### Test 8: `testRealWorldOrderScenario()`
**Purpose**: Simulate an actual restaurant order

**Scenario**: Customer orders totaling €45.50

**Example Order Breakdown**:
```
Appetizer:     €12.00
Main Course:   €18.50
Dessert:       €8.00
Beverage:      €7.00
──────────────────────
Total (TTC):   €45.50
```

**What Restaurant Needs to Know**:
- Base revenue: €41.36 (for profitability analysis)
- VAT collected: €4.14 (for tax remittance)
- Total: €45.50 (customer payment)

**Mathematical Verification**:
```
€45.50 / 1.10 = €41.363636... → €41.36 (base)
€45.50 - €41.36 = €4.14 (tax)
Cross-check: €41.36 + €4.14 = €45.50 ✓
```

**What We Test**:
```php
$orderTotal = 45.50;
$result = $this->taxCalculationService->calculateTaxFromTTC($orderTotal);

$this->assertEquals(41.36, $result['amountWithoutTax'], '', 0.01);
$this->assertEquals(4.14, $result['taxAmount'], '', 0.01);

// Additional verification
$calculatedTotal = $result['amountWithoutTax'] + $result['taxAmount'];
$this->assertEquals($orderTotal, $calculatedTotal, '', 0.01);
```

**Why This Test is Important**:
- ✅ Validates real-world accuracy
- ✅ Ensures financial reconciliation works
- ✅ Confirms reporting will be correct
- ✅ Builds confidence in the system

---

### Test 9: `testBidirectionalConversion()`
**Purpose**: Validate mathematical consistency

**Scenario**: Convert TTC → HT → back to TTC

**Process Flow**:
```
1. Start: €99.99 (TTC)
2. Calculate HT: €99.99 / 1.10 = €90.90 (rounded)
3. Calculate back: €90.90 × 1.10 = €99.99
4. Verify: Back to original (or very close)
```

**Why "Very Close" Instead of Exact**:
Floating-point arithmetic introduces tiny rounding errors:
```
€99.99 → €90.90 → €99.989999999...
```
We use a delta of €0.02 to account for this across two operations.

**What We Test**:
```php
$originalAmount = 99.99;

// Step 1: TTC → HT
$resultTTC = $this->taxCalculationService->calculateTaxFromTTC($originalAmount);
$amountHT = $resultTTC['amountWithoutTax'];

// Step 2: HT → TTC
$resultHT = $this->taxCalculationService->calculateTaxFromHT($amountHT);
$finalAmount = $resultHT['amountWithTax'];

// Verify round-trip accuracy
$this->assertEquals($originalAmount, $finalAmount, '', 0.02);
```

**What This Catches**:
- ❌ Formula errors (e.g., multiplying when should divide)
- ❌ Accumulated rounding errors
- ❌ Precision loss in calculations
- ❌ Type coercion issues

---

### Test 10: `testReturnValueTypes()`
**Purpose**: Ensure type consistency

**Scenario**: Validate all return values are floats

**Why Types Matter in PHP**:
```php
// Type issues can cause problems:
"10" + "20" = 30           // Works, but string concatenation
10 + 20 = 30               // Proper addition

"10" == 10                 // true (loose comparison)
"10" === 10                // false (strict comparison)
```

**What We Test**:
```php
$result = $this->taxCalculationService->calculateTaxFromTTC(100.0);

$this->assertIsFloat($result['amountWithoutTax']);
$this->assertIsFloat($result['taxAmount']);
$this->assertIsFloat($result['amountWithTax']);
$this->assertIsFloat($result['taxRate']);
```

**Why This Matters**:
- 🔄 **API Integration**: Other systems expect specific types
- 📦 **JSON Encoding**: `100.0` vs `"100.00"` in JSON
- 🧮 **Math Operations**: Prevents accidental string concatenation
- 💾 **Database Storage**: Type matching for proper storage

---

### Test 11: `testLargeAmounts()`
**Purpose**: Validate accuracy with large transactions

**Scenario**: Banquet order of €1,500.50

**Real-World Examples**:
- 👰 Wedding reception for 100 guests
- 🏢 Corporate event catering
- 🎉 Large party bookings
- 🍽️ Multi-course banquet service

**Why Test Large Amounts Specifically**:
```
Small error in small amount:  €0.01 on €10.00 = 0.1% error
Small error in large amount:  €0.01 on €1,500 = 0.0007% error

But accumulated errors matter:
1000 transactions × €0.01 = €10 discrepancy
```

**Mathematical Breakdown**:
```
€1,500.50 / 1.10 = €1,364.09 (base price)
€1,500.50 - €1,364.09 = €136.41 (VAT)
```

**What We Test**:
```php
$largeAmount = 1500.50;
$result = $this->taxCalculationService->calculateTaxFromTTC($largeAmount);

$this->assertEquals(1364.09, $result['amountWithoutTax'], '', 0.01);
$this->assertEquals(136.41, $result['taxAmount'], '', 0.01);
$this->assertEquals(1500.50, $result['amountWithTax']);
```

**What This Validates**:
- ✅ No precision loss with large numbers
- ✅ Rounding still works correctly
- ✅ Business-critical transactions are accurate
- ✅ Tax reporting is correct for all transaction sizes

---

## 🎨 Testing Patterns Used

### AAA Pattern (Arrange-Act-Assert)

Every test follows this structure:

```php
public function testExample(): void
{
    // ARRANGE: Set up test data and conditions
    $amountWithTax = 110.0;

    // ACT: Execute the method under test
    $result = $this->taxCalculationService->calculateTaxFromTTC($amountWithTax);

    // ASSERT: Verify the results
    $this->assertEquals(100.0, $result['amountWithoutTax']);
}
```

**Why This Pattern**:
- 📖 **Readability**: Clear structure makes tests easy to understand
- 🔍 **Debugging**: Easy to identify which part failed
- 🎯 **Focus**: Each test has a clear purpose
- 🔄 **Maintainability**: Consistent structure across all tests

---

## 💡 Assertion Types Explained

### `assertEquals(expected, actual, message, delta)`
Checks if two values are equal (with optional tolerance)

```php
$this->assertEquals(100.0, $result['amountWithoutTax']);
// Fails if not equal

$this->assertEquals(14.54, $result['amountWithoutTax'], '', 0.01);
// Passes if within €0.01 of 14.54 (14.53 to 14.55)
```

### `assertIsArray($value)`
Verifies the value is an array

```php
$this->assertIsArray($result);
// Ensures we got an array back, not null or other type
```

### `assertArrayHasKey($key, $array)`
Checks if an array has a specific key

```php
$this->assertArrayHasKey('amountWithoutTax', $result);
// Ensures the response has all required fields
```

### `assertIsFloat($value)`
Verifies the value is a floating-point number

```php
$this->assertIsFloat($result['taxRate']);
// Ensures proper type for mathematical operations
```

---

## 🚀 How to Run These Tests

### Run All Tests in This File
```powershell
php bin/phpunit tests/Unit/Service/TaxCalculationServiceTest.php
```

### Run with Readable Output
```powershell
php bin/phpunit --testdox tests/Unit/Service/TaxCalculationServiceTest.php
```

**Output**:
```
Tax Calculation Service
 ✔ Calculate tax from t t c
 ✔ Calculate tax from h t
 ✔ Calculate tax from t t c with rounding
 ✔ Calculate tax from t t c with zero amount
 ✔ Calculate tax from h t with zero amount
 ✔ Calculate tax with different tax rates
 ✔ Get tax rate
 ✔ Real world order scenario
 ✔ Bidirectional conversion
 ✔ Return value types
 ✔ Large amounts

OK (11 tests, 43 assertions)
```

### Run a Specific Test
```powershell
php bin/phpunit --filter testCalculateTaxFromTTC tests/Unit/Service/TaxCalculationServiceTest.php
```

### Run with Coverage (requires Xdebug)
```powershell
php bin/phpunit --coverage-html coverage/ tests/Unit/Service/TaxCalculationServiceTest.php
```

---

## 📊 Test Results Interpretation

### ✅ All Tests Pass
```
...........                                                       11 / 11 (100%)
OK (11 tests, 43 assertions)
```
- `.` = one successful test
- `11 / 11` = all 11 tests passed
- `43 assertions` = 43 individual checks passed

### ❌ Test Failure
```
F..........                                                       11 / 11 (100%)
FAILURES!
Tests: 11, Assertions: 42, Failures: 1.
```
- `F` = Failed test
- Detailed error message follows
- Shows expected vs actual values

### ⚠️ Test Error
```
E..........                                                       11 / 11 (100%)
ERRORS!
Tests: 11, Assertions: 10, Errors: 1.
```
- `E` = Error (exception thrown)
- Usually indicates a code problem, not just wrong value

---

## 📈 What We Achieved

### Coverage Statistics
- **11 test methods** covering all service methods
- **43 assertions** validating multiple aspects
- **100% functional coverage** of TaxCalculationService
- **Multiple scenarios**: normal, edge cases, real-world

### Quality Assurance
✅ **Mathematical accuracy** validated  
✅ **Edge cases** handled properly  
✅ **Type safety** ensured  
✅ **Real-world scenarios** tested  
✅ **Large amounts** work correctly  
✅ **Multiple tax rates** supported  
✅ **Floating-point issues** accounted for  

---

## 🎓 Key Concepts Learned

### 1. Unit Testing
Testing individual components in isolation

### 2. Mock Objects
Simulating dependencies for isolated testing

### 3. AAA Pattern
Arrange-Act-Assert structure for clear tests

### 4. Assertions
Checks that must be true for test to pass

### 5. Delta Tolerance
Allowing small variance for floating-point calculations

### 6. Edge Case Testing
Testing boundary conditions (zero, large values)

### 7. Type Safety
Ensuring consistent data types

### 8. Financial Precision
Proper rounding for monetary calculations

---

## 🔗 Related Files

| File | Purpose |
|------|---------|
| `TaxCalculationServiceTest.php` | The test file (this file documents) |
| `src/Service/TaxCalculationService.php` | The service being tested |
| `src/Service/RestaurantSettingsService.php` | Dependency (mocked in tests) |

---

## ⏭️ Next Steps

After mastering this test, you can:

1. ✅ **Write similar tests** for other services
2. ✅ **Add integration tests** (with real database)
3. ✅ **Create functional tests** (test full API endpoints)
4. ✅ **Measure code coverage** (aim for 80%+)
5. ✅ **Integrate with CI/CD** (automated testing)

---

## ❓ Frequently Asked Questions

**Q: Why 11 tests for such a simple service?**  
A: Tax calculations are financially and legally critical. Comprehensive testing prevents costly errors.

**Q: Why use mocks instead of real RestaurantSettingsService?**  
A: Mocks make tests faster, more reliable, and independent of configuration/database.

**Q: What's the delta parameter in assertEquals?**  
A: Allows small variance (±€0.01) to account for floating-point arithmetic limitations.

**Q: Why test with zero and large amounts?**  
A: Edge cases often reveal bugs that normal values don't expose.

**Q: How do I know if my tests are good enough?**  
A: Good tests should cover: normal operation, edge cases, error conditions, and real scenarios.

**Q: What if a test fails?**  
A: Read the error message carefully. It shows expected vs actual values and which assertion failed.

---

**Created**: October 21, 2025  
**Status**: ✅ All tests passing  
**PHPUnit Version**: 11.5.39  
**PHP Version**: 8.2.26  
**Test Execution Time**: ~0.02 seconds  
**Author**: Le Trois Quarts Development Team
