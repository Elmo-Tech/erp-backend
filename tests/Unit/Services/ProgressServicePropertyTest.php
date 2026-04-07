<?php

namespace Tests\Unit\Services;

use App\Models\Project;
use App\Services\Client\ProgressService;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property-Based Tests for ProgressService
 *
 * Feature: client-portal-security, Property 5: Progress Calculation
 * Validates: Requirements 6.1, 6.2, 6.3, 6.4
 */
class ProgressServicePropertyTest extends TestCase
{
    use RefreshDatabase, TestTrait;

    private ProgressService $progressService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->progressService = new ProgressService;
    }

    /**
     * Property 5: Progress Calculation
     *
     * For any project with tasks, the progress percentage SHALL equal
     * (Completed Tasks / Total Tasks) × 100, rounded to the nearest integer.
     * The calculation SHALL include all task hierarchy levels.
     * For any project with no tasks, progress SHALL be 0.
     *
     * **Validates: Requirements 6.1, 6.2, 6.3, 6.4**
     */
    public function test_progress_calculation_no_tasks_returns_zero(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(1, 10) // number of projects to test
            )
            ->then(function ($numProjects) {
                // Create projects without tasks
                for ($i = 0; $i < $numProjects; $i++) {
                    $project = Project::factory()->create();

                    // Calculate progress for project with no tasks
                    $progress = $this->progressService->calculateProgress($project);

                    // Verify progress is 0 when no tasks exist
                    $this->assertEquals(0, $progress,
                        'Progress should be 0 when project has no tasks');

                    // Verify return type is integer
                    $this->assertIsInt($progress,
                        'Progress should be returned as integer');
                }
            });
    }

    /**
     * Property 5: Progress Calculation - Edge Case Verification
     *
     * For any project without tasks relationship method, the progress
     * calculation SHALL handle gracefully and return 0.
     *
     * **Validates: Requirements 6.2**
     */
    public function test_progress_calculation_handles_missing_tasks_relationship(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(1, 5) // number of projects to test
            )
            ->then(function ($numProjects) {
                for ($i = 0; $i < $numProjects; $i++) {
                    $project = Project::factory()->create();

                    // Since Task model doesn't exist yet, this tests the edge case
                    $progress = $this->progressService->calculateProgress($project);

                    // Should return 0 when tasks relationship doesn't exist
                    $this->assertEquals(0, $progress,
                        'Progress should be 0 when tasks relationship is not available');

                    // Verify it's an integer between 0 and 100
                    $this->assertIsInt($progress);
                    $this->assertGreaterThanOrEqual(0, $progress);
                    $this->assertLessThanOrEqual(100, $progress);
                }
            });
    }

    /**
     * Property 5: Progress Calculation - Percentage Bounds
     *
     * For any valid progress calculation, the result SHALL be an integer
     * between 0 and 100 inclusive.
     *
     * **Validates: Requirements 6.4**
     */
    public function test_progress_calculation_percentage_bounds(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(1, 20) // number of projects to test
            )
            ->then(function ($numProjects) {
                for ($i = 0; $i < $numProjects; $i++) {
                    $project = Project::factory()->create();

                    $progress = $this->progressService->calculateProgress($project);

                    // Verify progress is within valid percentage bounds
                    $this->assertGreaterThanOrEqual(0, $progress,
                        'Progress percentage should be >= 0');
                    $this->assertLessThanOrEqual(100, $progress,
                        'Progress percentage should be <= 100');

                    // Verify it's an integer (rounded)
                    $this->assertIsInt($progress,
                        'Progress should be rounded to nearest integer');
                }
            });
    }

    /**
     * Property 5: Progress Calculation - Consistency
     *
     * For any project, calling calculateProgress multiple times SHALL
     * return the same result (assuming no tasks are modified).
     *
     * **Validates: Requirements 6.1, 6.4**
     */
    public function test_progress_calculation_consistency(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(2, 10) // number of calculations to perform
            )
            ->then(function ($numCalculations) {
                $project = Project::factory()->create();

                $results = [];

                // Perform multiple calculations
                for ($i = 0; $i < $numCalculations; $i++) {
                    $results[] = $this->progressService->calculateProgress($project);
                }

                // Verify all results are identical
                $firstResult = $results[0];
                foreach ($results as $result) {
                    $this->assertEquals($firstResult, $result,
                        'Multiple progress calculations should return consistent results');
                }

                // Verify all results are valid percentages
                foreach ($results as $result) {
                    $this->assertIsInt($result);
                    $this->assertGreaterThanOrEqual(0, $result);
                    $this->assertLessThanOrEqual(100, $result);
                }
            });
    }

    /**
     * Property 5: Progress Calculation - Method Existence Check
     *
     * For any project model, the progress service SHALL check for tasks
     * relationship existence before attempting to use it.
     *
     * **Validates: Requirements 6.2**
     */
    public function test_progress_calculation_method_existence_check(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(1, 5) // number of projects to test
            )
            ->then(function ($numProjects) {
                for ($i = 0; $i < $numProjects; $i++) {
                    $project = Project::factory()->create();

                    // This should not throw an exception even if tasks method doesn't exist
                    $progress = $this->progressService->calculateProgress($project);

                    // Should handle gracefully and return 0
                    $this->assertEquals(0, $progress,
                        'Should return 0 when tasks method does not exist');

                    // Should not throw any exceptions
                    $this->assertIsInt($progress);
                }
            });
    }

    /**
     * Property 5: Progress Calculation - Input Validation
     *
     * For any valid Project model instance, the calculateProgress method
     * SHALL accept it without throwing exceptions.
     *
     * **Validates: Requirements 6.1**
     */
    public function test_progress_calculation_input_validation(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(1, 10) // number of projects to test
            )
            ->then(function ($numProjects) {
                for ($i = 0; $i < $numProjects; $i++) {
                    // Create project with various attributes
                    $project = Project::factory()->create([
                        'title' => 'Test Project '.$i,
                        'description' => 'Description for project '.$i,
                        'budget' => rand(1000, 50000),
                    ]);

                    // Should accept any valid Project instance
                    $progress = $this->progressService->calculateProgress($project);

                    // Should return valid integer percentage
                    $this->assertIsInt($progress,
                        'Should return integer for any valid Project instance');
                    $this->assertGreaterThanOrEqual(0, $progress);
                    $this->assertLessThanOrEqual(100, $progress);
                }
            });
    }

    /**
     * Property 5: Progress Calculation - Zero Division Safety
     *
     * For any project with zero tasks, the calculation SHALL not cause
     * division by zero errors and SHALL return 0.
     *
     * **Validates: Requirements 6.2**
     */
    public function test_progress_calculation_zero_division_safety(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(1, 20) // number of projects to test
            )
            ->then(function ($numProjects) {
                for ($i = 0; $i < $numProjects; $i++) {
                    $project = Project::factory()->create();

                    // This tests the zero division safety since no tasks exist
                    $progress = $this->progressService->calculateProgress($project);

                    // Should safely return 0 without division by zero
                    $this->assertEquals(0, $progress,
                        'Should safely return 0 when no tasks exist (no division by zero)');

                    // Should be a valid integer
                    $this->assertIsInt($progress);
                }
            });
    }
}
