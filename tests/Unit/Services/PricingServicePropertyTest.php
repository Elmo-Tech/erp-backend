<?php

namespace Tests\Unit\Services;

use App\Modules\Sales\Services\PricingService;
use Eris\Generator;
use Eris\TestTrait;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Property-Based Tests for PricingService
 *
 * Feature: sales-pricing-engine, Property 1: Pricing Formula Correctness
 * Validates: Requirements 3.1, 3.2, 3.3, 3.4
 */
class PricingServicePropertyTest extends TestCase
{
    use TestTrait;

    private PricingService $pricingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricingService = new PricingService;
    }

    /**
     * Property 1: Pricing Formula Correctness
     *
     * For any valid estimations array, hourly rate > 0, and discount percentage between 0-100,
     * the PricingService SHALL calculate:
     * - totalHours = sum of all hour values in estimations
     * - grossPrice = totalHours × hourlyRate
     * - discountAmount = grossPrice × (discountPercentage / 100)
     * - finalPrice = grossPrice - discountAmount
     *
     * **Validates: Requirements 3.1, 3.2, 3.3, 3.4**
     */
    public function test_pricing_formula_correctness(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(0, 100), // frontendHours
                Generator\choose(0, 100), // backendHours
                Generator\choose(0, 100), // uiUxHours
                Generator\choose(0, 100), // testingHours
                Generator\choose(0, 100), // pmHours
                Generator\choose(1, 1000), // hourlyRate
                Generator\choose(0, 100)   // discountPercentage
            )
            ->then(function ($frontendHours, $backendHours, $uiUxHours, $testingHours, $pmHours, $hourlyRate, $discountPercentage) {
                $estimations = [
                    'frontendHours' => $frontendHours,
                    'backendHours' => $backendHours,
                    'uiUxHours' => $uiUxHours,
                    'testingHours' => $testingHours,
                    'pmHours' => $pmHours,
                ];

                $result = $this->pricingService->calculateQuote($estimations, $hourlyRate, $discountPercentage);

                // Calculate expected values manually
                $expectedTotalHours = array_sum($estimations);
                $expectedGrossPrice = $expectedTotalHours * $hourlyRate;
                $expectedDiscountAmount = $expectedGrossPrice * ($discountPercentage / 100);
                $expectedFinalPrice = $expectedGrossPrice - $expectedDiscountAmount;

                // Verify the formula calculations
                $this->assertEquals($expectedTotalHours, $result['totalHours'],
                    'Total hours should equal sum of all estimation hours');

                $this->assertEquals(round($expectedGrossPrice, 2), $result['grossPrice'],
                    'Gross price should equal totalHours × hourlyRate');

                $this->assertEquals(round($expectedDiscountAmount, 2), $result['discountAmount'],
                    'Discount amount should equal grossPrice × (discountPercentage / 100)');

                $this->assertEquals(round($expectedFinalPrice, 2), $result['finalPrice'],
                    'Final price should equal grossPrice - discountAmount');

                // Verify result structure
                $this->assertArrayHasKey('totalHours', $result);
                $this->assertArrayHasKey('grossPrice', $result);
                $this->assertArrayHasKey('discountAmount', $result);
                $this->assertArrayHasKey('finalPrice', $result);

                // Verify all values are non-negative
                $this->assertGreaterThanOrEqual(0, $result['totalHours']);
                $this->assertGreaterThanOrEqual(0, $result['grossPrice']);
                $this->assertGreaterThanOrEqual(0, $result['discountAmount']);
                $this->assertGreaterThanOrEqual(0, $result['finalPrice']);
            });
    }

    /**
     * Property 1: Pricing Formula Correctness - Custom Estimation Categories
     *
     * For any estimations array with custom categories beyond standard fields,
     * the total hours calculation SHALL include all hour values.
     *
     * **Validates: Requirements 3.1, 4.5**
     */
    public function test_pricing_formula_with_custom_categories(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(0, 50), // frontendHours
                Generator\choose(0, 50), // backendHours
                Generator\choose(0, 30), // customCategory1
                Generator\choose(0, 30), // anotherCustomCategory
                Generator\choose(1, 200), // hourlyRate
                Generator\choose(0, 50)   // discountPercentage
            )
            ->then(function ($frontendHours, $backendHours, $customHours1, $customHours2, $hourlyRate, $discountPercentage) {
                $estimations = [
                    'frontendHours' => $frontendHours,
                    'backendHours' => $backendHours,
                    'customCategory1' => $customHours1,
                    'anotherCustomCategory' => $customHours2,
                ];

                $result = $this->pricingService->calculateQuote($estimations, $hourlyRate, $discountPercentage);

                // Calculate expected total including custom categories
                $expectedTotalHours = array_sum($estimations);

                $this->assertEquals($expectedTotalHours, $result['totalHours'],
                    'Total hours should include all categories including custom ones');

                // Verify the custom categories are included in calculations
                $expectedGrossPrice = $expectedTotalHours * $hourlyRate;
                $this->assertEquals(round($expectedGrossPrice, 2), $result['grossPrice']);
            });
    }

    /**
     * Property 1: Pricing Formula Correctness - Zero Hours Edge Case
     *
     * When all estimation hours are zero, the pricing calculation
     * SHALL return zero for all monetary values.
     *
     * **Validates: Requirements 3.1, 3.2, 3.3, 3.4**
     */
    public function test_pricing_formula_with_zero_hours(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(1, 500), // hourlyRate
                Generator\choose(0, 100)  // discountPercentage
            )
            ->then(function ($hourlyRate, $discountPercentage) {
                $estimations = [
                    'frontendHours' => 0,
                    'backendHours' => 0,
                    'uiUxHours' => 0,
                    'testingHours' => 0,
                    'pmHours' => 0,
                ];

                $result = $this->pricingService->calculateQuote($estimations, $hourlyRate, $discountPercentage);

                $this->assertEquals(0, $result['totalHours']);
                $this->assertEquals(0.0, $result['grossPrice']);
                $this->assertEquals(0.0, $result['discountAmount']);
                $this->assertEquals(0.0, $result['finalPrice']);
            });
    }

    /**
     * Property 1: Pricing Formula Correctness - Maximum Discount Edge Case
     *
     * When discount percentage is 100%, the final price SHALL be zero
     * regardless of hours and hourly rate.
     *
     * **Validates: Requirements 3.3, 3.4**
     */
    public function test_pricing_formula_with_maximum_discount(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\vector(5, Generator\choose(1, 50)), // estimation hours
                Generator\choose(1, 300) // hourlyRate
            )
            ->then(function ($hours, $hourlyRate) {
                $estimations = [
                    'frontendHours' => $hours[0],
                    'backendHours' => $hours[1],
                    'uiUxHours' => $hours[2],
                    'testingHours' => $hours[3],
                    'pmHours' => $hours[4],
                ];

                $result = $this->pricingService->calculateQuote($estimations, $hourlyRate, 100);

                $expectedTotalHours = array_sum($hours);
                $expectedGrossPrice = $expectedTotalHours * $hourlyRate;

                $this->assertEquals($expectedTotalHours, $result['totalHours']);
                $this->assertEquals(round($expectedGrossPrice, 2), $result['grossPrice']);
                $this->assertEquals(round($expectedGrossPrice, 2), $result['discountAmount']);
                $this->assertEquals(0.0, $result['finalPrice']);
            });
    }

    /**
     * Property 1: Pricing Formula Correctness - Decimal Hours Precision
     *
     * The pricing calculation SHALL handle decimal hour values correctly
     * and maintain precision in calculations.
     *
     * **Validates: Requirements 3.1, 3.2**
     */
    public function test_pricing_formula_with_decimal_hours(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(1, 999), // integer part (0-99)
                Generator\choose(1, 9),   // decimal part (0.1-0.9)
                Generator\choose(10, 500), // hourlyRate
                Generator\choose(0, 25)    // discountPercentage
            )
            ->then(function ($intPart, $decimalPart, $hourlyRate, $discountPercentage) {
                $decimalHours = $intPart + ($decimalPart / 10);

                $estimations = [
                    'frontendHours' => $decimalHours,
                    'backendHours' => 0,
                ];

                $result = $this->pricingService->calculateQuote($estimations, $hourlyRate, $discountPercentage);

                $expectedGrossPrice = $decimalHours * $hourlyRate;
                $expectedDiscountAmount = $expectedGrossPrice * ($discountPercentage / 100);
                $expectedFinalPrice = $expectedGrossPrice - $expectedDiscountAmount;

                $this->assertEquals($decimalHours, $result['totalHours']);
                $this->assertEquals(round($expectedGrossPrice, 2), $result['grossPrice']);
                $this->assertEquals(round($expectedDiscountAmount, 2), $result['discountAmount']);
                $this->assertEquals(round($expectedFinalPrice, 2), $result['finalPrice']);
            });
    }

    /**
     * Property 3: Input Validation Rejects Invalid Values
     *
     * For any estimation with negative hour values, OR hourly rate ≤ 0,
     * OR discount percentage outside 0-100 range, the PricingService
     * SHALL throw an InvalidArgumentException.
     *
     * **Validates: Requirements 3.8, 3.9, 4.2**
     */
    public function test_input_validation_rejects_invalid_values(): void
    {
        // Test negative hour values
        $this
            ->limitTo(2)
            ->forAll(
                Generator\choose(-100, -1), // negative hours
                Generator\choose(1, 200),   // valid hourlyRate
                Generator\choose(0, 100)    // valid discountPercentage
            )
            ->then(function ($negativeHours, $hourlyRate, $discountPercentage) {
                $estimations = [
                    'frontendHours' => abs($negativeHours), // valid
                    'backendHours' => $negativeHours,       // invalid (negative)
                ];

                $this->expectException(InvalidArgumentException::class);
                $this->expectExceptionMessage('cannot be negative');

                $this->pricingService->calculateQuote($estimations, $hourlyRate, $discountPercentage);
            });
    }

    /**
     * Property 3: Input Validation Rejects Invalid Hourly Rate
     *
     * For any hourly rate ≤ 0, the PricingService SHALL throw an InvalidArgumentException.
     *
     * **Validates: Requirements 3.9**
     */
    public function test_input_validation_rejects_invalid_hourly_rate(): void
    {
        $this
            ->limitTo(2)
            ->forAll(
                Generator\choose(-100, 0),  // invalid hourlyRate (≤ 0)
                Generator\choose(0, 100)    // valid discountPercentage
            )
            ->then(function ($invalidHourlyRate, $discountPercentage) {
                $estimations = [
                    'frontendHours' => 10,
                    'backendHours' => 5,
                ];

                $this->expectException(InvalidArgumentException::class);
                $this->expectExceptionMessage('Hourly rate must be greater than zero');

                $this->pricingService->calculateQuote($estimations, $invalidHourlyRate, $discountPercentage);
            });
    }

    /**
     * Property 3: Input Validation Rejects Invalid Discount Percentage
     *
     * For any discount percentage outside 0-100 range, the PricingService
     * SHALL throw an InvalidArgumentException.
     *
     * **Validates: Requirements 4.2**
     */
    public function test_input_validation_rejects_invalid_discount_percentage(): void
    {
        // Test discount percentage > 100
        $this
            ->limitTo(25)
            ->forAll(
                Generator\choose(101, 200), // invalid discountPercentage (> 100)
                Generator\choose(1, 200)    // valid hourlyRate
            )
            ->then(function ($invalidDiscountPercentage, $hourlyRate) {
                $estimations = [
                    'frontendHours' => 10,
                    'backendHours' => 5,
                ];

                $this->expectException(InvalidArgumentException::class);
                $this->expectExceptionMessage('Discount percentage must be between 0 and 100');

                $this->pricingService->calculateQuote($estimations, $hourlyRate, $invalidDiscountPercentage);
            });

        // Test discount percentage < 0
        $this
            ->limitTo(25)
            ->forAll(
                Generator\choose(-100, -1), // invalid discountPercentage (< 0)
                Generator\choose(1, 200)    // valid hourlyRate
            )
            ->then(function ($invalidDiscountPercentage, $hourlyRate) {
                $estimations = [
                    'frontendHours' => 10,
                    'backendHours' => 5,
                ];

                $this->expectException(InvalidArgumentException::class);
                $this->expectExceptionMessage('Discount percentage must be between 0 and 100');

                $this->pricingService->calculateQuote($estimations, $hourlyRate, $invalidDiscountPercentage);
            });
    }

    /**
     * Property 3: Input Validation Rejects Non-Numeric Hour Values
     *
     * For any estimation with non-numeric hour values, the PricingService
     * SHALL throw an InvalidArgumentException.
     *
     * **Validates: Requirements 3.8**
     */
    public function test_input_validation_rejects_non_numeric_hours(): void
    {
        $this
            ->limitTo(2)
            ->forAll(
                Generator\elements(['string', 'invalid', 'abc', '10abc', 'null', 'false']), // non-numeric values
                Generator\choose(1, 200),   // valid hourlyRate
                Generator\choose(0, 100)    // valid discountPercentage
            )
            ->then(function ($nonNumericValue, $hourlyRate, $discountPercentage) {
                $estimations = [
                    'frontendHours' => 10,
                    'backendHours' => $nonNumericValue, // invalid (non-numeric)
                ];

                $this->expectException(InvalidArgumentException::class);
                $this->expectExceptionMessage('Invalid hour value');

                $this->pricingService->calculateQuote($estimations, $hourlyRate, $discountPercentage);
            });
    }

    /**
     * Property 4: Estimations Case Conversion Round Trip
     *
     * For any estimations object with camelCase keys, converting to snake_case
     * for storage and back to camelCase for API response SHALL produce an
     * equivalent object to the original input.
     *
     * **Validates: Requirements 4.3, 4.4**
     */
    public function test_estimations_case_conversion_round_trip(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(0, 100), // frontendHours
                Generator\choose(0, 100), // backendHours
                Generator\choose(0, 100), // uiUxHours
                Generator\choose(0, 100), // testingHours
                Generator\choose(0, 100), // pmHours
                Generator\choose(0, 50),  // customCategoryHours
                Generator\choose(0, 50)   // anotherCustomHours
            )
            ->then(function ($frontendHours, $backendHours, $uiUxHours, $testingHours, $pmHours, $customHours1, $customHours2) {
                // Original camelCase estimations
                $originalEstimations = [
                    'frontendHours' => $frontendHours,
                    'backendHours' => $backendHours,
                    'uiUxHours' => $uiUxHours,
                    'testingHours' => $testingHours,
                    'pmHours' => $pmHours,
                    'customCategoryHours' => $customHours1,
                    'anotherCustomHours' => $customHours2,
                ];

                // Convert to snake_case (for database storage)
                $snakeCaseEstimations = $this->pricingService->convertEstimationsToSnakeCase($originalEstimations);

                // Convert back to camelCase (for API response)
                $roundTripEstimations = $this->pricingService->convertEstimationsToCamelCase($snakeCaseEstimations);

                // Verify round trip produces equivalent object
                $this->assertEquals($originalEstimations, $roundTripEstimations,
                    'Round trip conversion should produce equivalent object to original');

                // Verify snake_case conversion worked correctly
                $expectedSnakeCase = [
                    'frontend_hours' => $frontendHours,
                    'backend_hours' => $backendHours,
                    'ui_ux_hours' => $uiUxHours,
                    'testing_hours' => $testingHours,
                    'pm_hours' => $pmHours,
                    'custom_category_hours' => $customHours1,
                    'another_custom_hours' => $customHours2,
                ];

                $this->assertEquals($expectedSnakeCase, $snakeCaseEstimations,
                    'camelCase to snake_case conversion should work correctly');

                // Verify all keys are properly converted
                foreach ($originalEstimations as $key => $value) {
                    $this->assertArrayHasKey($key, $roundTripEstimations,
                        "Round trip should preserve key: {$key}");
                    $this->assertEquals($value, $roundTripEstimations[$key],
                        "Round trip should preserve value for key: {$key}");
                }

                // Verify snake_case keys don't exist in round trip result
                foreach ($snakeCaseEstimations as $snakeKey => $value) {
                    $this->assertArrayNotHasKey($snakeKey, $roundTripEstimations,
                        "Round trip result should not contain snake_case key: {$snakeKey}");
                }
            });
    }

    /**
     * Property 4: Estimations Case Conversion Round Trip - Edge Cases
     *
     * For edge cases like single character keys, numbers in keys, and
     * already snake_case keys, the conversion SHALL handle them correctly.
     *
     * **Validates: Requirements 4.3, 4.4**
     */
    public function test_estimations_case_conversion_edge_cases(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(0, 50), // value1
                Generator\choose(0, 50), // value2
                Generator\choose(0, 50), // value3
                Generator\choose(0, 50)  // value4
            )
            ->then(function ($value1, $value2, $value3, $value4) {
                // Test various edge case key formats
                $originalEstimations = [
                    'a' => $value1,                    // single character
                    'apiHours' => $value2,             // starts with lowercase
                    'uiUx2Hours' => $value3,           // contains numbers
                    'veryLongCategoryNameHours' => $value4, // long key
                ];

                // Convert to snake_case and back
                $snakeCaseEstimations = $this->pricingService->convertEstimationsToSnakeCase($originalEstimations);
                $roundTripEstimations = $this->pricingService->convertEstimationsToCamelCase($snakeCaseEstimations);

                // Verify round trip preserves original structure
                $this->assertEquals($originalEstimations, $roundTripEstimations,
                    'Round trip should handle edge case keys correctly');

                // Verify specific snake_case conversions
                $this->assertArrayHasKey('a', $snakeCaseEstimations);
                $this->assertArrayHasKey('api_hours', $snakeCaseEstimations);
                $this->assertArrayHasKey('ui_ux2_hours', $snakeCaseEstimations);
                $this->assertArrayHasKey('very_long_category_name_hours', $snakeCaseEstimations);
            });
    }

    /**
     * Property 4: Estimations Case Conversion Round Trip - Empty and Single Key
     *
     * For empty arrays and single key arrays, the conversion SHALL work correctly.
     *
     * **Validates: Requirements 4.3, 4.4**
     */
    public function test_estimations_case_conversion_empty_and_single(): void
    {
        // Test empty array
        $emptyEstimations = [];
        $snakeCaseEmpty = $this->pricingService->convertEstimationsToSnakeCase($emptyEstimations);
        $roundTripEmpty = $this->pricingService->convertEstimationsToCamelCase($snakeCaseEmpty);

        $this->assertEquals($emptyEstimations, $roundTripEmpty,
            'Round trip should handle empty array correctly');

        // Test single key with property-based values
        $this
            ->limitTo(2)
            ->forAll(
                Generator\choose(0, 100) // single value
            )
            ->then(function ($singleValue) {
                $singleKeyEstimations = ['frontendHours' => $singleValue];

                $snakeCaseSingle = $this->pricingService->convertEstimationsToSnakeCase($singleKeyEstimations);
                $roundTripSingle = $this->pricingService->convertEstimationsToCamelCase($snakeCaseSingle);

                $this->assertEquals($singleKeyEstimations, $roundTripSingle,
                    'Round trip should handle single key array correctly');

                $this->assertEquals(['frontend_hours' => $singleValue], $snakeCaseSingle,
                    'Single key should convert to snake_case correctly');
            });
    }

    /**
     * Property 5: Custom Estimation Categories Preserved
     *
     * For any estimations object containing custom hour categories beyond the
     * standard fields (frontendHours, backendHours, etc.), the custom categories
     * SHALL be preserved through storage and retrieval, and included in total
     * hours calculation.
     *
     * **Validates: Requirements 4.5**
     */
    public function test_custom_estimation_categories_preserved(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(0, 50),  // standard frontendHours
                Generator\choose(0, 50),  // standard backendHours
                Generator\choose(0, 30),  // custom category 1
                Generator\choose(0, 30),  // custom category 2
                Generator\choose(0, 25),  // custom category 3
                Generator\choose(1, 200), // hourlyRate
                Generator\choose(0, 50)   // discountPercentage
            )
            ->then(function ($frontendHours, $backendHours, $customHours1, $customHours2, $customHours3, $hourlyRate, $discountPercentage) {
                // Create estimations with both standard and custom categories
                $estimations = [
                    // Standard categories
                    'frontendHours' => $frontendHours,
                    'backendHours' => $backendHours,
                    // Custom categories with various naming patterns
                    'customDevelopmentHours' => $customHours1,
                    'thirdPartyIntegrationHours' => $customHours2,
                    'specialConsultingHours' => $customHours3,
                ];

                // Test that custom categories are included in pricing calculation
                $result = $this->pricingService->calculateQuote($estimations, $hourlyRate, $discountPercentage);

                // Calculate expected total including all categories (standard + custom)
                $expectedTotalHours = array_sum($estimations);

                $this->assertEquals($expectedTotalHours, $result['totalHours'],
                    'Total hours should include all categories including custom ones');

                // Verify that custom categories contribute to pricing calculations
                $expectedGrossPrice = $expectedTotalHours * $hourlyRate;
                $expectedDiscountAmount = $expectedGrossPrice * ($discountPercentage / 100);
                $expectedFinalPrice = $expectedGrossPrice - $expectedDiscountAmount;

                $this->assertEquals(round($expectedGrossPrice, 2), $result['grossPrice'],
                    'Gross price should include custom category hours');
                $this->assertEquals(round($expectedDiscountAmount, 2), $result['discountAmount'],
                    'Discount amount should be calculated on total including custom categories');
                $this->assertEquals(round($expectedFinalPrice, 2), $result['finalPrice'],
                    'Final price should include custom category hours');

                // Test case conversion preservation for custom categories
                $snakeCaseEstimations = $this->pricingService->convertEstimationsToSnakeCase($estimations);
                $roundTripEstimations = $this->pricingService->convertEstimationsToCamelCase($snakeCaseEstimations);

                // Verify custom categories are preserved through case conversion
                $this->assertEquals($estimations, $roundTripEstimations,
                    'Custom categories should be preserved through case conversion');

                // Verify specific custom category preservation
                $this->assertArrayHasKey('customDevelopmentHours', $roundTripEstimations,
                    'Custom category "customDevelopmentHours" should be preserved');
                $this->assertArrayHasKey('thirdPartyIntegrationHours', $roundTripEstimations,
                    'Custom category "thirdPartyIntegrationHours" should be preserved');
                $this->assertArrayHasKey('specialConsultingHours', $roundTripEstimations,
                    'Custom category "specialConsultingHours" should be preserved');

                // Verify custom category values are preserved
                $this->assertEquals($customHours1, $roundTripEstimations['customDevelopmentHours'],
                    'Custom category value should be preserved');
                $this->assertEquals($customHours2, $roundTripEstimations['thirdPartyIntegrationHours'],
                    'Custom category value should be preserved');
                $this->assertEquals($customHours3, $roundTripEstimations['specialConsultingHours'],
                    'Custom category value should be preserved');

                // Verify snake_case conversion for custom categories
                $this->assertArrayHasKey('custom_development_hours', $snakeCaseEstimations,
                    'Custom category should convert to snake_case correctly');
                $this->assertArrayHasKey('third_party_integration_hours', $snakeCaseEstimations,
                    'Custom category should convert to snake_case correctly');
                $this->assertArrayHasKey('special_consulting_hours', $snakeCaseEstimations,
                    'Custom category should convert to snake_case correctly');
            });
    }

    /**
     * Property 5: Custom Estimation Categories Preserved - Edge Cases
     *
     * For edge cases of custom categories (single character, numbers, special patterns),
     * the custom categories SHALL be preserved correctly.
     *
     * **Validates: Requirements 4.5**
     */
    public function test_custom_estimation_categories_preserved_edge_cases(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(0, 30), // value for single char category
                Generator\choose(0, 30), // value for numbered category
                Generator\choose(0, 30), // value for mixed case category
                Generator\choose(1, 100) // hourlyRate
            )
            ->then(function ($value1, $value2, $value3, $hourlyRate) {
                // Test various edge case custom category names
                $estimations = [
                    'a' => $value1,                           // single character
                    'category2Hours' => $value2,              // contains numbers
                    'veryLongCustomCategoryNameHours' => $value3, // very long name
                ];

                // Test pricing calculation includes all custom categories
                $result = $this->pricingService->calculateQuote($estimations, $hourlyRate, 0);

                $expectedTotalHours = array_sum($estimations);
                $this->assertEquals($expectedTotalHours, $result['totalHours'],
                    'Total hours should include all custom categories regardless of naming pattern');

                // Test case conversion preservation
                $snakeCaseEstimations = $this->pricingService->convertEstimationsToSnakeCase($estimations);
                $roundTripEstimations = $this->pricingService->convertEstimationsToCamelCase($snakeCaseEstimations);

                $this->assertEquals($estimations, $roundTripEstimations,
                    'Edge case custom categories should be preserved through case conversion');

                // Verify specific edge case conversions
                $this->assertArrayHasKey('a', $snakeCaseEstimations,
                    'Single character custom category should be preserved');
                $this->assertArrayHasKey('category2_hours', $snakeCaseEstimations,
                    'Numbered custom category should convert correctly');
                $this->assertArrayHasKey('very_long_custom_category_name_hours', $snakeCaseEstimations,
                    'Long custom category should convert correctly');
            });
    }

    /**
     * Property 5: Custom Estimation Categories Preserved - Only Custom Categories
     *
     * For estimations containing ONLY custom categories (no standard fields),
     * the system SHALL handle them correctly.
     *
     * **Validates: Requirements 4.5**
     */
    public function test_custom_estimation_categories_only_custom(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(1, 50), // custom category 1 (ensure non-zero)
                Generator\choose(1, 50), // custom category 2 (ensure non-zero)
                Generator\choose(1, 50), // custom category 3 (ensure non-zero)
                Generator\choose(1, 200) // hourlyRate
            )
            ->then(function ($customHours1, $customHours2, $customHours3, $hourlyRate) {
                // Create estimations with ONLY custom categories (no standard ones)
                $estimations = [
                    'researchHours' => $customHours1,
                    'documentationHours' => $customHours2,
                    'deploymentHours' => $customHours3,
                ];

                // Test pricing calculation works with only custom categories
                $result = $this->pricingService->calculateQuote($estimations, $hourlyRate, 0);

                $expectedTotalHours = array_sum($estimations);
                $expectedGrossPrice = $expectedTotalHours * $hourlyRate;

                $this->assertEquals($expectedTotalHours, $result['totalHours'],
                    'Total hours should work with only custom categories');
                $this->assertEquals(round($expectedGrossPrice, 2), $result['grossPrice'],
                    'Gross price should be calculated correctly with only custom categories');
                $this->assertEquals(round($expectedGrossPrice, 2), $result['finalPrice'],
                    'Final price should be calculated correctly with only custom categories');

                // Test case conversion works with only custom categories
                $snakeCaseEstimations = $this->pricingService->convertEstimationsToSnakeCase($estimations);
                $roundTripEstimations = $this->pricingService->convertEstimationsToCamelCase($snakeCaseEstimations);

                $this->assertEquals($estimations, $roundTripEstimations,
                    'Only custom categories should be preserved through case conversion');

                // Verify no standard categories are accidentally added
                $standardCategories = ['frontendHours', 'backendHours', 'uiUxHours', 'testingHours', 'pmHours'];
                foreach ($standardCategories as $standardCategory) {
                    $this->assertArrayNotHasKey($standardCategory, $roundTripEstimations,
                        "Standard category {$standardCategory} should not be added when not present");
                }
            });
    }
}
