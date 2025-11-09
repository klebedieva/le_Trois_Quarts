<?php

namespace App\Tests\Unit\Service;

use App\Entity\Coupon;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Service\TaxCalculationService;
use App\Service\RestaurantSettingsService;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for TaxCalculationService
 * 
 * This test suite validates the tax calculation logic for the restaurant application.
 * It covers various scenarios including standard calculations, edge cases, and different VAT rates.
 * 
 * French Tax Context:
 * - Restaurant service (dine-in): 10% VAT
 * - Takeaway food: 5.5% VAT
 * - Standard rate: 20% VAT
 * 
 * Terminology:
 * - TTC (Toutes Taxes Comprises): Price including tax
 * - HT (Hors Taxes): Price excluding tax
 * - VAT (Value Added Tax): TVA in French
 * 
 * @package App\Tests\Unit\Service
 * @author Le Trois Quarts Development Team
 */
class TaxCalculationServiceTest extends TestCase
{
    /**
     * The service under test - handles all tax calculations
     * 
     * @var TaxCalculationService
     */
    private TaxCalculationService $taxCalculationService;

    /**
     * Mock of the restaurant settings service used to control VAT rate during tests.
     *
     * @var RestaurantSettingsService&\PHPUnit\Framework\MockObject\MockObject
     */
    private RestaurantSettingsService $restaurantSettings;

    /**
     * Set up the test environment before each test method
     * 
     * This method runs automatically before every test. It creates a clean environment
     * by instantiating a fresh TaxCalculationService with a mocked RestaurantSettingsService.
     * The mock is configured to return a 10% VAT rate, which is the standard rate for
     * restaurant dine-in services in France.
     * 
     * Why use mocks?
     * - Isolates the unit under test from external dependencies
     * - Provides predictable behavior for the dependency
     * - Allows testing without database or configuration files
     * - Makes tests fast and reliable
     * 
     * @return void
     */
    protected function setUp(): void
    {
        // Create a mock object for RestaurantSettingsService
        // This simulates the settings service without needing actual configuration
        $this->restaurantSettings = $this->createMock(RestaurantSettingsService::class);
        
        // Configure the mock to return 10% (0.10) when getVatRate() is called
        // This represents the standard French restaurant VAT rate for dine-in service
        $this->restaurantSettings
            ->method('getVatRate')
            ->willReturn(0.10);
        
        // Instantiate the service under test with the mocked dependency
        // This is the actual service we'll be testing in all test methods
        $this->taxCalculationService = new TaxCalculationService($this->restaurantSettings);
    }

    /**
     * Test: Calculate tax breakdown from price including tax (TTC → HT)
     * 
     * Scenario: Menu item priced at €110 (including 10% VAT)
     * Expected Result: €100 base price + €10 tax
     * 
     * This test validates the core functionality of extracting the tax component
     * from a price that already includes VAT. This is the most common use case
     * in the restaurant as menu prices are displayed with tax included.
     * 
     * Mathematical Formula:
     * HT = TTC / (1 + VAT_RATE)
     * €100 = €110 / (1 + 0.10)
     * €100 = €110 / 1.10
     * 
     * Tax Amount = TTC - HT
     * €10 = €110 - €100
     * 
     * @return void
     */
    public function testCalculateTaxFromTTC(): void
    {
        // ARRANGE: Prepare test data
        // Set up a menu item price of €110 including 10% VAT
        $amountWithTax = 110.0;

        // ACT: Execute the method under test
        // Call the service to break down the tax-inclusive price
        $result = $this->taxCalculationService->calculateTaxFromTTC($amountWithTax);

        // ASSERT: Verify the result structure
        // First, ensure we got an array back (not null or other type)
        $this->assertIsArray($result);
        
        // Verify all required keys are present in the response
        $this->assertArrayHasKey('amountWithoutTax', $result);
        $this->assertArrayHasKey('taxAmount', $result);
        $this->assertArrayHasKey('amountWithTax', $result);
        $this->assertArrayHasKey('taxRate', $result);

        // ASSERT: Verify the calculation accuracy
        // Base price should be €100 (€110 / 1.10)
        $this->assertEquals(100.0, $result['amountWithoutTax'], 'Amount excluding VAT should be €100.0');
        
        // Tax amount should be €10 (€110 - €100)
        $this->assertEquals(10.0, $result['taxAmount'], 'VAT amount should be €10.0');
        
        // Total should remain €110 (unchanged from input)
        $this->assertEquals(110.0, $result['amountWithTax'], 'Amount including VAT should be €110.0');
        
        // VAT rate should be 10% (0.10)
        $this->assertEquals(0.10, $result['taxRate'], 'VAT rate should be 0.10 (10%)');
    }

    /**
     * Test: Calculate tax-inclusive price from base price (HT → TTC)
     * 
     * Scenario: Wholesale/base price of €100 (excluding VAT)
     * Expected Result: €110 total price (€100 + €10 tax)
     * 
     * This test validates the calculation of the final customer price when starting
     * with a base price that doesn't include tax. This is useful for:
     * - Calculating markup from supplier costs
     * - Determining final selling price from cost basis
     * - Invoice generation for business customers who need tax breakdown
     * 
     * Mathematical Formula:
     * Tax Amount = HT × VAT_RATE
     * €10 = €100 × 0.10
     * 
     * TTC = HT + Tax Amount
     * €110 = €100 + €10
     * 
     * Or simplified: TTC = HT × (1 + VAT_RATE)
     * €110 = €100 × 1.10
     * 
     * @return void
     */
    public function testCalculateTaxFromHT(): void
    {
        // ARRANGE: Prepare test data
        // Set up a base price of €100 excluding VAT (HT = Hors Taxes)
        $amountWithoutTax = 100.0;

        // ACT: Execute the method under test
        // Calculate the final price including VAT
        $result = $this->taxCalculationService->calculateTaxFromHT($amountWithoutTax);

        // ASSERT: Verify the calculation results
        // Ensure the response is an array with all necessary information
        $this->assertIsArray($result);
        
        // Base amount should remain €100 (unchanged from input)
        $this->assertEquals(100.0, $result['amountWithoutTax'], 'Base amount should remain €100.0');
        
        // Tax amount should be €10 (€100 × 0.10)
        $this->assertEquals(10.0, $result['taxAmount'], 'Tax amount should be €10.0');
        
        // Total with tax should be €110 (€100 + €10)
        $this->assertEquals(110.0, $result['amountWithTax'], 'Total with tax should be €110.0');
        
        // VAT rate should be 10%
        $this->assertEquals(0.10, $result['taxRate'], 'VAT rate should be 0.10 (10%)');
    }

    /**
     * Test: Rounding precision for decimal amounts
     * 
     * Scenario: Menu item priced at €15.99 (including VAT)
     * Expected Result: Properly rounded values to 2 decimal places
     * 
     * This test is critical for financial accuracy. It validates that the service
     * correctly handles floating-point arithmetic and rounds monetary values appropriately.
     * 
     * Why this matters:
     * - Currency should always have exactly 2 decimal places
     * - Improper rounding can lead to financial discrepancies
     * - Accumulated rounding errors can cause audit issues
     * - Legal requirement for tax reporting accuracy
     * 
     * Mathematical Breakdown:
     * €15.99 / 1.10 = €14.536363636... (repeating decimal)
     * Rounded to 2 decimals: €14.54 (base price)
     * Tax: €15.99 - €14.54 = €1.45
     * 
     * Note: The delta parameter (0.01) in assertEquals accounts for floating-point
     * precision issues inherent in computer arithmetic.
     * 
     * @return void
     */
    public function testCalculateTaxFromTTCWithRounding(): void
    {
        // ARRANGE: Prepare test data
        // Use a price that results in repeating decimals when divided by 1.10
        $amountWithTax = 15.99;

        // ACT: Execute the calculation
        $result = $this->taxCalculationService->calculateTaxFromTTC($amountWithTax);

        // ASSERT: Verify proper rounding
        // Mathematical calculation: €15.99 / 1.10 = €14.536363... → should round to €14.54
        $this->assertEquals(
            14.54, 
            $result['amountWithoutTax'], 
            'Base amount should be rounded to €14.54', 
            0.01  // Delta tolerance for floating-point comparison
        );
        
        // Tax amount: €15.99 - €14.54 = €1.45
        $this->assertEquals(
            1.45, 
            $result['taxAmount'], 
            'Tax amount should be rounded to €1.45', 
            0.01
        );
        
        // Original amount should remain unchanged
        $this->assertEquals(15.99, $result['amountWithTax'], 'Total should remain €15.99');
    }

    /**
     * Test: Calculation with zero amount (TTC → HT)
     * 
     * Scenario: Empty cart or zero-priced item (€0)
     * Expected Result: All values should be zero
     * 
     * This is an edge case test that ensures the service handles zero values gracefully
     * without division by zero errors or unexpected behavior.
     * 
     * Use cases:
     * - Empty shopping cart calculation
     * - Free promotional items
     * - Testing system behavior at boundary conditions
     * 
     * @return void
     */
    public function testCalculateTaxFromTTCWithZeroAmount(): void
    {
        // ARRANGE: Prepare edge case with zero amount
        $amountWithTax = 0.0;

        // ACT: Execute the calculation
        $result = $this->taxCalculationService->calculateTaxFromTTC($amountWithTax);

        // ASSERT: Verify all values are zero
        // Zero divided by any number should still be zero
        $this->assertEquals(0.0, $result['amountWithoutTax']);
        $this->assertEquals(0.0, $result['taxAmount']);
        $this->assertEquals(0.0, $result['amountWithTax']);
    }

    /**
     * Test: Calculation with zero amount (HT → TTC)
     * 
     * Scenario: Zero base price
     * Expected Result: All calculated values should be zero
     * 
     * Complementary test to the previous one, testing the reverse calculation
     * direction with a zero input.
     * 
     * @return void
     */
    public function testCalculateTaxFromHTWithZeroAmount(): void
    {
        // ARRANGE: Prepare edge case with zero base amount
        $amountWithoutTax = 0.0;

        // ACT: Execute the calculation
        $result = $this->taxCalculationService->calculateTaxFromHT($amountWithoutTax);

        // ASSERT: Verify all values are zero
        // Zero multiplied by any tax rate should still be zero
        $this->assertEquals(0.0, $result['amountWithoutTax']);
        $this->assertEquals(0.0, $result['taxAmount']);
        $this->assertEquals(0.0, $result['amountWithTax']);
    }

    /**
     * Test: Validation with different VAT rates
     * 
     * Scenario: France has multiple VAT rates depending on the type of service
     * - 10% for restaurant dine-in service
     * - 5.5% for takeaway food
     * - 20% for standard goods and services
     * 
     * This test ensures the service can correctly handle different tax rates,
     * which is crucial for restaurants that offer both dine-in and takeaway services.
     * 
     * Real-world context:
     * The French government applies reduced VAT rates to food services to make them
     * more affordable. The rate changes based on how the food is consumed:
     * - Eating in the restaurant: 10% (service component)
     * - Takeaway: 5.5% (food product only, no service)
     * - Alcoholic beverages: 20% (standard rate)
     * 
     * @return void
     */
    public function testCalculateTaxWithDifferentTaxRates(): void
    {
        // TEST CASE 1: Takeaway food with 5.5% VAT
        // Create a new mock configured for the reduced takeaway rate
        $restaurantSettings = $this->createMock(RestaurantSettingsService::class);
        $restaurantSettings->method('getVatRate')->willReturn(0.055);
        $taxService = new TaxCalculationService($restaurantSettings);

        // Calculate: €105.50 / 1.055 = €100 (base) + €5.50 (tax)
        $result = $taxService->calculateTaxFromTTC(105.5);
        
        $this->assertEquals(100.0, $result['amountWithoutTax'], 'With 5.5% VAT: €100 base price', 0.01);
        $this->assertEquals(5.5, $result['taxAmount'], 'With 5.5% VAT: €5.50 tax', 0.01);
        $this->assertEquals(0.055, $result['taxRate']);

        // TEST CASE 2: Standard rate of 20% VAT (e.g., alcoholic beverages)
        // Create a new mock configured for the standard rate
        $restaurantSettings = $this->createMock(RestaurantSettingsService::class);
        $restaurantSettings->method('getVatRate')->willReturn(0.20);
        $taxService = new TaxCalculationService($restaurantSettings);

        // Calculate: €120 / 1.20 = €100 (base) + €20 (tax)
        $result = $taxService->calculateTaxFromTTC(120.0);
        
        $this->assertEquals(100.0, $result['amountWithoutTax'], 'With 20% VAT: €100 base price', 0.01);
        $this->assertEquals(20.0, $result['taxAmount'], 'With 20% VAT: €20 tax', 0.01);
        $this->assertEquals(0.20, $result['taxRate']);
    }

    /**
     * Test: Retrieve current tax rate
     * 
     * Scenario: Get the currently configured VAT rate from the service
     * Expected Result: Should return 0.10 (10%) as configured in setUp()
     * 
     * This test verifies that the service correctly retrieves and returns the
     * tax rate from its configuration. This is useful for:
     * - Displaying tax information to users
     * - Generating tax reports
     * - Validating configuration
     * 
     * @return void
     */
    public function testGetTaxRate(): void
    {
        // ACT: Retrieve the configured tax rate
        $taxRate = $this->taxCalculationService->getTaxRate();

        // ASSERT: Verify the rate value and type
        // Should be 10% (0.10) as configured in the setUp() method
        $this->assertEquals(0.10, $taxRate);
        
        // Ensure it's returned as a float for mathematical operations
        $this->assertIsFloat($taxRate);
    }

    /**
     * Test: Real-world order scenario
     * 
     * Scenario: Customer places an order totaling €45.50 (including VAT)
     * Expected Result: Proper breakdown of base price and tax amount
     * 
     * This test simulates an actual restaurant order to ensure the service
     * produces accurate results for real transactions. This is critical for:
     * - End-of-day financial reconciliation
     * - Tax reporting to authorities
     * - Accounting and bookkeeping
     * - Customer receipt generation
     * 
     * Example Use Case:
     * A customer orders a main course (€18.50), an appetizer (€12.00), 
     * and a dessert (€8.00), plus drinks (€7.00) = €45.50 total
     * 
     * The restaurant needs to know:
     * - How much is the base amount for cost calculation?
     * - How much VAT was collected for tax remittance?
     * 
     * Mathematical Verification:
     * €45.50 / 1.10 = €41.363636... → €41.36 (base)
     * €45.50 - €41.36 = €4.14 (tax)
     * Verification: €41.36 + €4.14 = €45.50 ✓
     * 
     * @return void
     */
    public function testRealWorldOrderScenario(): void
    {
        // ARRANGE: Set up a realistic order total
        // This represents a typical 2-person meal at the restaurant
        $orderTotal = 45.50;

        // ACT: Calculate the tax breakdown
        $result = $this->taxCalculationService->calculateTaxFromTTC($orderTotal);

        // ASSERT: Verify the calculated values
        // Base amount: €45.50 / 1.10 = €41.36
        $this->assertEquals(41.36, $result['amountWithoutTax'], 'Base price should be €41.36', 0.01);
        
        // Tax amount: €45.50 - €41.36 = €4.14
        $this->assertEquals(4.14, $result['taxAmount'], 'Tax amount should be €4.14', 0.01);
        
        // Total should match input
        $this->assertEquals(45.50, $result['amountWithTax'], 'Total should be €45.50');

        // ASSERT: Cross-verify that numbers add up correctly
        // This additional check ensures mathematical consistency
        $calculatedTotal = $result['amountWithoutTax'] + $result['taxAmount'];
        $this->assertEquals($orderTotal, $calculatedTotal, 'Base + Tax should equal Total', 0.01);
    }

    /**
     * Test: Bidirectional conversion (TTC → HT → TTC)
     * 
     * Scenario: Convert price with tax to base price, then back to price with tax
     * Expected Result: Should return to the original value (within rounding tolerance)
     * 
     * This test validates the mathematical consistency of the service by performing
     * a round-trip calculation. If the conversions are correct, we should get back
     * to approximately the same value we started with.
     * 
     * Why "approximately"?
     * Due to floating-point arithmetic and rounding at each step, we may have
     * minor differences (typically less than €0.02). This is acceptable for
     * financial calculations and handled with a delta tolerance.
     * 
     * Process Flow:
     * 1. Start with €99.99 (TTC)
     * 2. Calculate HT: €99.99 / 1.10 = €90.90
     * 3. Calculate back to TTC: €90.90 × 1.10 = €99.99
     * 4. Verify we're back at (or very close to) €99.99
     * 
     * This test helps catch:
     * - Rounding errors
     * - Formula mistakes
     * - Precision loss in calculations
     * 
     * @return void
     */
    public function testBidirectionalConversion(): void
    {
        // ARRANGE: Start with a price including tax
        $originalAmount = 99.99;

        // ACT Step 1: Convert TTC → HT (extract base price)
        $resultTTC = $this->taxCalculationService->calculateTaxFromTTC($originalAmount);
        $amountHT = $resultTTC['amountWithoutTax'];

        // ACT Step 2: Convert HT → TTC (add tax back)
        $resultHT = $this->taxCalculationService->calculateTaxFromHT($amountHT);
        $finalAmount = $resultHT['amountWithTax'];

        // ASSERT: Verify we returned to the original amount
        // Allow €0.02 tolerance for floating-point rounding across two operations
        $this->assertEquals(
            $originalAmount, 
            $finalAmount, 
            'Bidirectional conversion should return to original value', 
            0.02  // Slightly larger delta due to two conversion operations
        );
    }

    /**
     * Test: Verify return value data types
     * 
     * Scenario: Validate that all returned values have correct data types
     * Expected Result: All monetary values should be floats
     * 
     * This test ensures type safety and consistency in the service's return values.
     * While PHP is dynamically typed, maintaining consistent types is important for:
     * - Integration with other systems expecting specific types
     * - JSON serialization (floats vs strings)
     * - Mathematical operations without type juggling
     * - Database storage type matching
     * 
     * Type consistency helps prevent bugs like:
     * - String concatenation instead of addition ("10" + "20" vs 10 + 20)
     * - Comparison issues ("10" == 10 but "10" !== 10)
     * - JSON encoding differences
     * 
     * @return void
     */
    public function testReturnValueTypes(): void
    {
        // ACT: Perform a calculation
        $result = $this->taxCalculationService->calculateTaxFromTTC(100.0);

        // ASSERT: Verify all numeric values are returned as floats
        // Financial calculations should always use float type for precision
        $this->assertIsFloat($result['amountWithoutTax'], 'Base amount should be float');
        $this->assertIsFloat($result['taxAmount'], 'Tax amount should be float');
        $this->assertIsFloat($result['amountWithTax'], 'Total amount should be float');
        $this->assertIsFloat($result['taxRate'], 'Tax rate should be float');
    }

    /**
     * Test: Calculation accuracy with large amounts
     * 
     * Scenario: Large banquet order of €1,500.50 (including VAT)
     * Expected Result: Accurate calculation without precision loss
     * 
     * This test validates that the service maintains calculation accuracy even with
     * large monetary values. Large transactions are common for:
     * - Wedding receptions
     * - Corporate events
     * - Large group bookings
     * - Catering services
     * 
     * Why test large amounts specifically?
     * - Floating-point errors can accumulate with larger numbers
     * - Rounding issues may become more pronounced
     * - Business-critical to maintain accuracy on high-value transactions
     * - Tax authorities require precise reporting regardless of amount
     * 
     * Real-world example:
     * A wedding reception for 100 guests at €15/person = €1,500
     * Plus additional services bringing total to €1,500.50
     * 
     * Mathematical breakdown:
     * €1,500.50 / 1.10 = €1,364.09 (base price)
     * €1,500.50 - €1,364.09 = €136.41 (VAT at 10%)
     * 
     * @return void
     */
    public function testLargeAmounts(): void
    {
        // ARRANGE: Set up a large transaction amount
        // This simulates a significant catering event or banquet
        $largeAmount = 1500.50;

        // ACT: Calculate tax breakdown
        $result = $this->taxCalculationService->calculateTaxFromTTC($largeAmount);

        // ASSERT: Verify calculation accuracy for large amount
        // Base amount: €1,500.50 / 1.10 = €1,364.09
        $this->assertEquals(
            1364.09, 
            $result['amountWithoutTax'], 
            'Base amount should be €1,364.09 for large transaction', 
            0.01
        );
        
        // Tax amount: €1,500.50 - €1,364.09 = €136.41
        $this->assertEquals(
            136.41, 
            $result['taxAmount'], 
            'Tax amount should be €136.41 for large transaction', 
            0.01
        );
        
        // Total should match input
        $this->assertEquals(
            1500.50, 
            $result['amountWithTax'], 
            'Total should remain €1,500.50'
        );
    }

    /**
     * Test: Full order recomputation without discounts.
     *
     * Ensures TaxCalculationService::applyOrderTotals() updates subtotal, VAT,
     * and total (including delivery fees) using the current VAT rate.
     */
    public function testApplyOrderTotalsWithoutDiscounts(): void
    {
        $order = new Order();
        $order->setDeliveryFee('5.00');

        $itemA = (new OrderItem())
            ->setUnitPrice('12.00')
            ->setQuantity(2);
        $itemB = (new OrderItem())
            ->setUnitPrice('6.00')
            ->setQuantity(1);

        $order->addItem($itemA);
        $order->addItem($itemB);

        $this->taxCalculationService->applyOrderTotals($order);

        $this->assertSame('27.27', $order->getSubtotal(), 'Subtotal (HT) should reflect TTC / (1 + VAT).');
        $this->assertSame('2.73', $order->getTaxAmount(), 'Tax amount should be TTC - HT.');
        $this->assertSame('35.00', $order->getTotal(), 'Total should include delivery fee (30 + 5).');
        $this->assertSame('0.00', $order->getDiscountAmount(), 'No discount expected for base scenario.');
    }

    /**
     * Test: Order recomputation with coupon and manual discount scenarios.
     *
     * Verifies coupons automatically override discount amounts and manual discounts
     * are clamped to the order total to avoid negative balances.
     */
    public function testApplyOrderTotalsWithCouponAndManualDiscount(): void
    {
        // Coupon scenario
        $couponOrder = new Order();
        $couponOrder->setDeliveryFee('5.00');

        $couponOrder->addItem(
            (new OrderItem())->setUnitPrice('10.00')->setQuantity(2)
        );
        $couponOrder->addItem(
            (new OrderItem())->setUnitPrice('10.00')->setQuantity(1)
        );

        $coupon = (new Coupon())
            ->setCode('PROMO10')
            ->setDiscountType(Coupon::TYPE_PERCENTAGE)
            ->setDiscountValue('10.00');

        $couponOrder->setCoupon($coupon);

        $this->taxCalculationService->applyOrderTotals($couponOrder);

        $this->assertSame('27.27', $couponOrder->getSubtotal(), 'Subtotal should exclude VAT even with coupon applied.');
        $this->assertSame('2.73', $couponOrder->getTaxAmount(), 'Tax should remain based on TTC before coupon.');
        $this->assertSame('3.50', $couponOrder->getDiscountAmount(), 'Coupon should apply a 10% discount on (30 + 5).');
        $this->assertSame('31.50', $couponOrder->getTotal(), 'Total should subtract coupon discount.');

        // Manual discount scenario with clamping
        $manualOrder = new Order();
        $manualOrder->setDeliveryFee('0.00');
        $manualOrder->addItem(
            (new OrderItem())->setUnitPrice('15.00')->setQuantity(2)
        );
        $manualOrder->setDiscountAmount('50.00'); // Exceeds order amount intentionally

        $this->taxCalculationService->applyOrderTotals($manualOrder);

        $this->assertSame('27.27', $manualOrder->getSubtotal(), 'Subtotal should remain based on TTC without delivery.');
        $this->assertSame('2.73', $manualOrder->getTaxAmount(), 'Tax remains based on TTC.');
        $this->assertSame('50.00', $manualOrder->getDiscountAmount(), 'Manual discount value should be preserved.');
        $this->assertSame('0.00', $manualOrder->getTotal(), 'Total should never be negative after discount clamping.');
    }
}

