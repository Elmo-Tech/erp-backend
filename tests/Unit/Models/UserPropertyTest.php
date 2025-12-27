<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property-Based Tests for User Model
 * 
 * Feature: phase-0-infrastructure, Property 3: User Internal Cost Hidden by Default
 * Feature: phase-0-infrastructure, Property 4: User Internal Cost Visible to Super Admin
 * Validates: Requirements 6.3, 6.4
 */
class UserPropertyTest extends TestCase
{
    use TestTrait, RefreshDatabase;

    /**
     * Property 3: User Internal Cost Hidden by Default
     * 
     * For any User model serialization (toArray, toJson), the internal_hourly_cost
     * field SHALL NOT appear in the output when using default serialization.
     * 
     * **Validates: Requirements 6.3**
     */
    public function test_internal_hourly_cost_is_hidden_in_default_serialization(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generator\float(),
                Generator\seq(Generator\string())
            )
            ->then(function ($hourlyCost, $skills) {
                // Ensure hourly cost is positive and reasonable
                $hourlyCost = abs($hourlyCost) % 10000;
                
                // Convert skills to array format
                $skillsArray = array_slice($skills, 0, 5);
                
                $user = User::factory()->create([
                    'internal_hourly_cost' => $hourlyCost,
                    'skills' => $skillsArray,
                ]);

                // Test toArray serialization
                $arrayOutput = $user->toArray();
                $this->assertArrayNotHasKey('internal_hourly_cost', $arrayOutput);
                
                // Test toJson serialization
                $jsonOutput = json_decode($user->toJson(), true);
                $this->assertArrayNotHasKey('internal_hourly_cost', $jsonOutput);
                
                // Verify password is also hidden
                $this->assertArrayNotHasKey('password', $arrayOutput);
                $this->assertArrayNotHasKey('remember_token', $arrayOutput);
                
                // Verify other fields are present
                $this->assertArrayHasKey('name', $arrayOutput);
                $this->assertArrayHasKey('email', $arrayOutput);
                $this->assertArrayHasKey('skills', $arrayOutput);
            });
    }

    /**
     * Property 3: User Internal Cost Hidden by Default - Verify internal_hourly_cost exists in database
     * 
     * For any User with internal_hourly_cost set, the value SHALL be stored
     * in the database but NOT exposed in default serialization.
     * 
     * **Validates: Requirements 6.3**
     */
    public function test_internal_hourly_cost_stored_but_hidden(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generator\choose(1, 99999)
            )
            ->then(function ($hourlyCostCents) {
                // Convert cents to dollars for decimal precision
                $hourlyCost = $hourlyCostCents / 100;
                
                $user = User::factory()->create([
                    'internal_hourly_cost' => $hourlyCost,
                ]);

                // Verify the value is stored in the database
                $this->assertDatabaseHas('users', [
                    'id' => $user->id,
                ]);
                
                // Verify we can access it directly on the model
                $this->assertEquals(
                    number_format($hourlyCost, 2, '.', ''),
                    $user->internal_hourly_cost
                );
                
                // But it should NOT appear in serialization
                $arrayOutput = $user->toArray();
                $this->assertArrayNotHasKey('internal_hourly_cost', $arrayOutput);
            });
    }

    /**
     * Property 4: User Internal Cost Visible to Super Admin
     * 
     * For any User model serialization requested by a Super Admin user,
     * the internal_hourly_cost field SHALL be conditionally included
     * in the output when explicitly requested via makeInternalCostVisible().
     * 
     * **Validates: Requirements 6.4**
     */
    public function test_internal_hourly_cost_visible_when_explicitly_requested(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generator\choose(1, 99999)
            )
            ->then(function ($hourlyCostCents) {
                // Convert cents to dollars for decimal precision
                $hourlyCost = $hourlyCostCents / 100;
                
                $user = User::factory()->create([
                    'internal_hourly_cost' => $hourlyCost,
                ]);

                // When makeInternalCostVisible() is called, the field should be visible
                $arrayOutput = $user->makeInternalCostVisible()->toArray();
                $this->assertArrayHasKey('internal_hourly_cost', $arrayOutput);
                $this->assertEquals(
                    number_format($hourlyCost, 2, '.', ''),
                    $arrayOutput['internal_hourly_cost']
                );
            });
    }

    /**
     * Property 4: User Internal Cost Visible to Super Admin - toArrayWithInternalCost method
     * 
     * For any User model, calling toArrayWithInternalCost() SHALL include
     * the internal_hourly_cost field in the output.
     * 
     * **Validates: Requirements 6.4**
     */
    public function test_to_array_with_internal_cost_includes_cost(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generator\choose(1, 99999)
            )
            ->then(function ($hourlyCostCents) {
                // Convert cents to dollars for decimal precision
                $hourlyCost = $hourlyCostCents / 100;
                
                $user = User::factory()->create([
                    'internal_hourly_cost' => $hourlyCost,
                ]);

                // toArrayWithInternalCost should include the internal_hourly_cost
                $arrayOutput = $user->toArrayWithInternalCost();
                $this->assertArrayHasKey('internal_hourly_cost', $arrayOutput);
                $this->assertEquals(
                    number_format($hourlyCost, 2, '.', ''),
                    $arrayOutput['internal_hourly_cost']
                );
                
                // But a fresh instance should still hide it by default
                $freshUser = User::find($user->id);
                $freshArrayOutput = $freshUser->toArray();
                $this->assertArrayNotHasKey('internal_hourly_cost', $freshArrayOutput);
            });
    }
}
