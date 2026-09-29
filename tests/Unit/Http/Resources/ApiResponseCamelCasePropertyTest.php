<?php

namespace Tests\Unit\Http\Resources;

use App\Enums\LeadSource;
use App\Enums\QuotationStatus;
use App\Http\Resources\V1\Leads\LeadResource;
use App\Http\Resources\V1\Quotations\QuotationResource;
use App\Http\Resources\V1\TimeEntries\TimeEntryResource;
use App\Models\Lead;
use App\Models\Quotation;
use App\Models\TimeEntry;
use App\Models\User;
use Eris\Generator;
use Eris\TestTrait;
use Tests\ErisDatabaseTestCase;

/**
 * Property-Based Tests for API Response camelCase Format
 *
 * Feature: sales-pricing-engine, Property 9: API Response camelCase Format
 * Validates: Requirements 1.6, 2.8, 5.8
 */
class ApiResponseCamelCasePropertyTest extends ErisDatabaseTestCase
{
    use TestTrait;

    /**
     * Property 9: API Response camelCase Format - Lead Resources
     *
     * For any Lead API response, all JSON keys SHALL be in camelCase format
     * (no snake_case keys in response body).
     *
     * **Validates: Requirements 1.6**
     */
    public function test_lead_resource_camel_case_format(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\names(), // name
                Generator\names(), // email base
                Generator\names(), // company
                Generator\elements(LeadSource::cases()), // source
                Generator\elements(['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'won', 'lost']) // status
            )
            ->then(function ($name, $emailBase, $company, $source, $status) {
                // Ensure we have valid data
                $name = $name ?: 'Test Name';
                $email = ($emailBase ?: 'test').'@example.com';

                // Create a user for audit fields
                $user = User::factory()->create();

                // Create a lead with the generated data
                $lead = Lead::factory()->create([
                    'name' => $name,
                    'email' => $email,
                    'company' => $company ?: 'Test Company',
                    'source' => $source ?? LeadSource::WEBSITE,
                    'status' => $status,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                // Transform using LeadResource
                $resource = new LeadResource($lead);
                $response = $resource->toArray(request());

                // Verify all keys are in camelCase format
                $this->assertCamelCaseKeys($response, 'LeadResource');

                // Verify specific expected camelCase keys exist
                $expectedCamelCaseKeys = [
                    'leadId', 'name', 'email', 'company', 'source', 'status',
                    'createdAt', 'updatedAt', 'createdBy', 'updatedBy',
                ];

                foreach ($expectedCamelCaseKeys as $key) {
                    $this->assertArrayHasKey($key, $response,
                        "LeadResource should contain camelCase key: {$key}");
                }

                // Verify no snake_case keys exist
                $forbiddenSnakeCaseKeys = [
                    'lead_id', 'created_at', 'updated_at', 'created_by', 'updated_by',
                ];

                foreach ($forbiddenSnakeCaseKeys as $key) {
                    $this->assertArrayNotHasKey($key, $response,
                        "LeadResource should NOT contain snake_case key: {$key}");
                }

                // Verify data integrity (values are preserved)
                $this->assertEquals($lead->id, $response['leadId']);
                $this->assertEquals($lead->name, $response['name']);
                $this->assertEquals($lead->email, $response['email']);
                $this->assertEquals($lead->status->value, $response['status']);
            });
    }

    /**
     * Property 9: API Response camelCase Format - Quotation Resources
     *
     * For any Quotation API response, all JSON keys SHALL be in camelCase format,
     * including converted estimations keys.
     *
     * **Validates: Requirements 2.8**
     */
    public function test_quotation_resource_camel_case_format(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(1, 100), // frontend_hours
                Generator\choose(1, 100), // backend_hours
                Generator\choose(1, 100), // ui_ux_hours
                Generator\choose(1, 100), // testing_hours
                Generator\choose(1, 100), // pm_hours
                Generator\choose(10, 500), // hourly_rate
                Generator\choose(0, 50),   // discount_percentage
                Generator\elements(QuotationStatus::cases()) // status
            )
            ->then(function ($frontendHours, $backendHours, $uiUxHours, $testingHours, $pmHours, $hourlyRate, $discountPercentage, $status) {
                // Create required models
                $user = User::factory()->create();
                $lead = Lead::factory()->create(['created_by' => $user->id]);

                // Create estimations in snake_case (as stored in database)
                $estimations = [
                    'frontend_hours' => $frontendHours,
                    'backend_hours' => $backendHours,
                    'ui_ux_hours' => $uiUxHours,
                    'testing_hours' => $testingHours,
                    'pm_hours' => $pmHours,
                ];

                $quotation = Quotation::factory()->create([
                    'lead_id' => $lead->id,
                    'estimations' => $estimations,
                    'hourly_rate' => $hourlyRate,
                    'discount_percentage' => $discountPercentage,
                    'final_price' => 1000.00, // Will be recalculated by resource
                    'currency' => 'USD',
                    'status' => $status,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                // Transform using QuotationResource
                $resource = new QuotationResource($quotation);
                $response = $resource->toArray(request());

                // Verify all top-level keys are in camelCase format
                $this->assertCamelCaseKeys($response, 'QuotationResource');

                // Verify specific expected camelCase keys exist
                $expectedCamelCaseKeys = [
                    'quotationId', 'leadId', 'estimations', 'hourlyRate',
                    'discountPercentage', 'finalPrice', 'currency', 'status',
                    'pricingBreakdown', 'createdAt', 'updatedAt', 'createdBy', 'updatedBy',
                ];

                foreach ($expectedCamelCaseKeys as $key) {
                    $this->assertArrayHasKey($key, $response,
                        "QuotationResource should contain camelCase key: {$key}");
                }

                // Verify estimations keys are converted to camelCase
                $this->assertIsArray($response['estimations']);
                $this->assertCamelCaseKeys($response['estimations'], 'QuotationResource estimations');

                // Verify specific estimation keys are in camelCase
                $expectedEstimationKeys = [
                    'frontendHours', 'backendHours', 'uiUxHours', 'testingHours', 'pmHours',
                ];

                foreach ($expectedEstimationKeys as $key) {
                    $this->assertArrayHasKey($key, $response['estimations'],
                        "Estimations should contain camelCase key: {$key}");
                }

                // Verify no snake_case keys exist in estimations
                $forbiddenEstimationKeys = [
                    'frontend_hours', 'backend_hours', 'ui_ux_hours', 'testing_hours', 'pm_hours',
                ];

                foreach ($forbiddenEstimationKeys as $key) {
                    $this->assertArrayNotHasKey($key, $response['estimations'],
                        "Estimations should NOT contain snake_case key: {$key}");
                }

                // Verify pricingBreakdown keys are camelCase
                if (! empty($response['pricingBreakdown'])) {
                    $this->assertCamelCaseKeys($response['pricingBreakdown'], 'QuotationResource pricingBreakdown');

                    $expectedBreakdownKeys = ['totalHours', 'grossPrice', 'discountAmount', 'finalPrice'];
                    foreach ($expectedBreakdownKeys as $key) {
                        $this->assertArrayHasKey($key, $response['pricingBreakdown'],
                            "PricingBreakdown should contain camelCase key: {$key}");
                    }
                }

                // Verify no top-level snake_case keys exist
                $forbiddenSnakeCaseKeys = [
                    'quotation_id', 'lead_id', 'hourly_rate', 'discount_percentage',
                    'final_price', 'created_at', 'updated_at', 'created_by', 'updated_by',
                ];

                foreach ($forbiddenSnakeCaseKeys as $key) {
                    $this->assertArrayNotHasKey($key, $response,
                        "QuotationResource should NOT contain snake_case key: {$key}");
                }
            });
    }

    /**
     * Property 9: API Response camelCase Format - TimeEntry Resources
     *
     * For any TimeEntry API response, all JSON keys SHALL be in camelCase format.
     *
     * **Validates: Requirements 5.8**
     */
    public function test_time_entry_resource_camel_case_format(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(1, 1440), // duration_minutes (1 minute to 24 hours)
                Generator\elements(['lead', 'quotation']) // trackable_type simplified
            )
            ->then(function ($durationMinutes, $trackableTypeSimple) {
                // Create required models
                $user = User::factory()->create();

                // Create trackable entity based on type
                if ($trackableTypeSimple === 'lead') {
                    $trackable = Lead::factory()->create(['created_by' => $user->id]);
                    $trackableType = 'App\\Models\\Lead';
                } else {
                    $lead = Lead::factory()->create(['created_by' => $user->id]);
                    $trackable = Quotation::factory()->create([
                        'lead_id' => $lead->id,
                        'created_by' => $user->id,
                    ]);
                    $trackableType = 'App\\Models\\Quotation';
                }

                // Create time entry
                $timeEntry = TimeEntry::factory()->create([
                    'user_id' => $user->id,
                    'trackable_id' => $trackable->id,
                    'trackable_type' => $trackableType,
                    'start_time' => now()->subMinutes($durationMinutes + 10),
                    'end_time' => now()->subMinutes(10),
                    'duration_minutes' => $durationMinutes,
                ]);

                // Load the trackable relationship for display name
                $timeEntry->load('trackable');

                // Transform using TimeEntryResource
                $resource = new TimeEntryResource($timeEntry);
                $response = $resource->toArray(request());

                // Verify all keys are in camelCase format
                $this->assertCamelCaseKeys($response, 'TimeEntryResource');

                // Verify specific expected camelCase keys exist
                $expectedCamelCaseKeys = [
                    'timeEntryId', 'userId', 'trackableId', 'trackableType',
                    'trackableDisplayName', 'startTime', 'endTime', 'durationMinutes',
                    'isActive', 'createdAt', 'updatedAt',
                ];

                foreach ($expectedCamelCaseKeys as $key) {
                    $this->assertArrayHasKey($key, $response,
                        "TimeEntryResource should contain camelCase key: {$key}");
                }

                // Verify no snake_case keys exist
                $forbiddenSnakeCaseKeys = [
                    'time_entry_id', 'user_id', 'trackable_id', 'trackable_type',
                    'start_time', 'end_time', 'duration_minutes', 'is_active',
                    'created_at', 'updated_at',
                ];

                foreach ($forbiddenSnakeCaseKeys as $key) {
                    $this->assertArrayNotHasKey($key, $response,
                        "TimeEntryResource should NOT contain snake_case key: {$key}");
                }

                // Verify data integrity (values are preserved)
                $this->assertEquals($timeEntry->id, $response['timeEntryId']);
                $this->assertEquals($timeEntry->user_id, $response['userId']);
                $this->assertEquals($timeEntry->trackable_id, $response['trackableId']);
                $this->assertEquals($timeEntry->trackable_type, $response['trackableType']);
                $this->assertEquals($timeEntry->duration_minutes, $response['durationMinutes']);
                $this->assertEquals($timeEntry->isActive(), $response['isActive']);
            });
    }

    /**
     * Property 9: API Response camelCase Format - Nested Resources
     *
     * For any API response containing nested resources (e.g., Quotation with Lead),
     * all nested keys SHALL also be in camelCase format.
     *
     * **Validates: Requirements 1.6, 2.8**
     */
    public function test_nested_resources_camel_case_format(): void
    {
        $this
            ->limitTo(2)
            ->forAll(
                Generator\names(), // lead name
                Generator\names(), // company
                Generator\choose(10, 200), // hourly_rate
                Generator\choose(0, 25)    // discount_percentage
            )
            ->then(function ($leadName, $company, $hourlyRate, $discountPercentage) {
                // Ensure we have valid data
                $leadName = $leadName ?: 'Test Lead';
                $company = $company ?: 'Test Company';

                // Create required models
                $user = User::factory()->create();
                $lead = Lead::factory()->create([
                    'name' => $leadName,
                    'company' => $company,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                $quotation = Quotation::factory()->create([
                    'lead_id' => $lead->id,
                    'hourly_rate' => $hourlyRate,
                    'discount_percentage' => $discountPercentage,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                // Load the lead relationship
                $quotation->load('lead');

                // Transform using QuotationResource (which includes nested LeadResource)
                $resource = new QuotationResource($quotation);
                $response = $resource->toArray(request());

                // Verify top-level keys are camelCase
                $this->assertCamelCaseKeys($response, 'QuotationResource (with nested Lead)');

                // Verify nested lead resource keys are camelCase (if present)
                if (isset($response['lead']) && is_array($response['lead'])) {
                    $this->assertCamelCaseKeys($response['lead'], 'Nested LeadResource');

                    // Verify specific nested lead keys
                    $expectedNestedKeys = ['leadId', 'name', 'email', 'company', 'createdAt', 'updatedAt'];
                    foreach ($expectedNestedKeys as $key) {
                        $this->assertArrayHasKey($key, $response['lead'],
                            "Nested LeadResource should contain camelCase key: {$key}");
                    }

                    // Verify no snake_case keys in nested resource
                    $forbiddenNestedKeys = ['lead_id', 'created_at', 'updated_at', 'created_by', 'updated_by'];
                    foreach ($forbiddenNestedKeys as $key) {
                        $this->assertArrayNotHasKey($key, $response['lead'],
                            "Nested LeadResource should NOT contain snake_case key: {$key}");
                    }
                }
            });
    }

    /**
     * Helper method to assert that all keys in an array are in camelCase format.
     *
     * @param  array  $array  The array to check
     * @param  string  $context  Context for error messages
     */
    private function assertCamelCaseKeys(array $array, string $context): void
    {
        foreach (array_keys($array) as $key) {
            // Skip non-string keys
            if (! is_string($key)) {
                continue;
            }

            // Check if key is in camelCase format
            $this->assertTrue(
                $this->isCamelCase($key),
                "Key '{$key}' in {$context} should be in camelCase format"
            );

            // Check if key contains underscores (snake_case indicator)
            $this->assertStringNotContainsString('_', $key,
                "Key '{$key}' in {$context} should not contain underscores (snake_case)");
        }
    }

    /**
     * Check if a string is in camelCase format.
     *
     * @param  string  $string  The string to check
     * @return bool True if the string is in camelCase format
     */
    private function isCamelCase(string $string): bool
    {
        // Empty strings are not camelCase
        if (empty($string)) {
            return false;
        }

        // Should start with lowercase letter
        if (! ctype_lower($string[0])) {
            return false;
        }

        // Should not contain underscores
        if (strpos($string, '_') !== false) {
            return false;
        }

        // Should not contain spaces
        if (strpos($string, ' ') !== false) {
            return false;
        }

        // Should only contain alphanumeric characters
        if (! ctype_alnum($string)) {
            return false;
        }

        return true;
    }
}
