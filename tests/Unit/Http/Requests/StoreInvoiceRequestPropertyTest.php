<?php

namespace Tests\Unit\Http\Requests;

use App\Modules\Invoice\Requests\Invoices\StoreInvoiceRequest;
use App\Modules\Project\Models\Project;
use App\Models\User;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Property-Based Tests for StoreInvoiceRequest Validation
 *
 * Feature: client-portal-security, Property 13: Invoice Amount Validation
 * Validates: Requirements 3.4
 */
class StoreInvoiceRequestPropertyTest extends TestCase
{
    use RefreshDatabase, TestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test project for validation
        $user = User::factory()->create();
        $client = $this->ensureClientProfile($user);
        $this->testProject = Project::factory()->create(['client_id' => $client->id]);
    }

    /**
     * Property 13: Invoice Amount Validation - Positive Amount Required
     *
     * For any invoice creation request, the amount field SHALL be required,
     * numeric, and greater than 0. Any amount less than or equal to 0 SHALL be rejected.
     *
     * **Validates: Requirements 3.4**
     */
    public function test_amount_must_be_positive_number(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\oneOf(
                    Generator\choose(-1000, 0),      // Zero and negative numbers
                    Generator\constant(-0.01),       // Small negative
                    Generator\constant(0.001),       // Below minimum
                    Generator\string(),              // Non-numeric strings
                    Generator\constant(null),        // Null values
                    Generator\constant('abc'),       // String values
                    Generator\constant('')           // Empty string
                )
            )
            ->then(function ($invalidAmount) {
                $requestData = [
                    'projectId' => $this->testProject->id,
                    'amount' => $invalidAmount,
                    'currency' => 'USD',
                    'dueDate' => now()->addDays(30)->format('Y-m-d'),
                    'notes' => 'Test invoice',
                ];

                $request = new StoreInvoiceRequest;
                $validator = Validator::make($requestData, $request->rules());

                // Invalid amounts should fail validation
                $this->assertTrue($validator->fails(), "Amount {$invalidAmount} should fail validation");
                $this->assertTrue($validator->errors()->has('amount'), "Amount validation should have errors for {$invalidAmount}");
            });
    }

    /**
     * Property 13: Invoice Amount Validation - Valid Positive Amounts Pass
     *
     * For any positive numeric amount greater than 0, the validation SHALL pass.
     *
     * **Validates: Requirements 3.4**
     */
    public function test_positive_amounts_pass_validation(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(1, 999999) // Only integer amounts for simplicity
            )
            ->then(function ($validAmount) {
                $requestData = [
                    'projectId' => $this->testProject->id,
                    'amount' => $validAmount,
                    'currency' => 'USD',
                    'dueDate' => now()->addDays(30)->format('Y-m-d'),
                    'notes' => 'Test invoice',
                ];

                $request = new StoreInvoiceRequest;
                $validator = Validator::make($requestData, $request->rules());

                // Valid positive amounts should pass validation
                $this->assertFalse($validator->fails());
                $this->assertFalse($validator->errors()->has('amount'));
            });
    }

    /**
     * Property 13: Invoice Amount Validation - Currency Format Validation
     *
     * For any currency field, it SHALL be exactly 3 uppercase letters or null.
     * Invalid currency formats SHALL be rejected.
     *
     * **Validates: Requirements 3.4**
     */
    public function test_currency_format_validation(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\oneOf(
                    Generator\constant('usd'),    // Lowercase
                    Generator\constant('US'),     // Too short
                    Generator\constant('USDD'),   // Too long
                    Generator\constant('123'),    // Numbers
                    Generator\constant('US1'),    // Mixed
                    Generator\constant('u s'),    // With spaces
                    Generator\constant('USD123'), // Too long with numbers
                    Generator\constant('us$')     // Special characters
                )
            )
            ->then(function ($invalidCurrency) {
                $requestData = [
                    'projectId' => $this->testProject->id,
                    'amount' => 100.00,
                    'currency' => $invalidCurrency,
                    'dueDate' => now()->addDays(30)->format('Y-m-d'),
                    'notes' => 'Test invoice',
                ];

                $request = new StoreInvoiceRequest;
                $validator = Validator::make($requestData, $request->rules());

                // Invalid currency formats should fail validation
                $this->assertTrue($validator->fails());
                $this->assertTrue($validator->errors()->has('currency'));
            });
    }

    /**
     * Property 13: Invoice Amount Validation - Currency Is Required
     *
     * For null or empty currency values, validation SHALL fail since currency is required.
     *
     * **Validates: Requirements 3.4**
     */
    public function test_currency_is_required(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\oneOf(
                    Generator\constant(null),
                    Generator\constant('')
                )
            )
            ->then(function ($nullableCurrency) {
                $requestData = [
                    'projectId' => $this->testProject->id,
                    'amount' => 100.00,
                    'currency' => $nullableCurrency,
                    'dueDate' => now()->addDays(30)->format('Y-m-d'),
                    'notes' => 'Test invoice',
                ];

                $request = new StoreInvoiceRequest;
                $validator = Validator::make($requestData, $request->rules());

                // Null/empty currency should fail validation
                $this->assertTrue($validator->fails());
                $this->assertTrue($validator->errors()->has('currency'));
            });
    }

    /**
     * Property 13: Invoice Amount Validation - Valid Currency Codes Pass
     *
     * For any valid 3-letter uppercase currency code, validation SHALL pass.
     *
     * **Validates: Requirements 3.4**
     */
    public function test_valid_currency_codes_pass(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\elements(['USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD', 'CHF', 'CNY'])
            )
            ->then(function ($validCurrency) {
                $requestData = [
                    'projectId' => $this->testProject->id,
                    'amount' => 100.00,
                    'currency' => $validCurrency,
                    'dueDate' => now()->addDays(30)->format('Y-m-d'),
                    'notes' => 'Test invoice',
                ];

                $request = new StoreInvoiceRequest;
                $validator = Validator::make($requestData, $request->rules());

                // Valid currency codes should pass validation
                $this->assertFalse($validator->fails());
                $this->assertFalse($validator->errors()->has('currency'));
            });
    }

    /**
     * Property 13: Invoice Amount Validation - Due Date Must Be Future
     *
     * For any due date that is today or in the past, validation SHALL fail.
     * Only future dates SHALL pass validation.
     *
     * **Validates: Requirements 3.4**
     */
    public function test_due_date_must_be_future(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(-365, 0) // Days in the past or today
            )
            ->then(function ($daysOffset) {
                $pastOrTodayDate = now()->addDays($daysOffset)->format('Y-m-d');

                $requestData = [
                    'projectId' => $this->testProject->id,
                    'amount' => 100.00,
                    'currency' => 'USD',
                    'dueDate' => $pastOrTodayDate,
                    'notes' => 'Test invoice',
                ];

                $request = new StoreInvoiceRequest;
                $validator = Validator::make($requestData, $request->rules());

                // Past or today dates should fail validation
                $this->assertTrue($validator->fails());
                $this->assertTrue($validator->errors()->has('dueDate'));
            });
    }

    /**
     * Property 13: Invoice Amount Validation - Future Due Dates Pass
     *
     * For any due date in the future, validation SHALL pass.
     *
     * **Validates: Requirements 3.4**
     */
    public function test_future_due_dates_pass(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(1, 365) // Days in the future
            )
            ->then(function ($daysOffset) {
                $futureDate = now()->addDays($daysOffset)->format('Y-m-d');

                $requestData = [
                    'projectId' => $this->testProject->id,
                    'amount' => 100.00,
                    'currency' => 'USD',
                    'dueDate' => $futureDate,
                    'notes' => 'Test invoice',
                ];

                $request = new StoreInvoiceRequest;
                $validator = Validator::make($requestData, $request->rules());

                // Future dates should pass validation
                $this->assertFalse($validator->fails());
                $this->assertFalse($validator->errors()->has('dueDate'));
            });
    }
}
