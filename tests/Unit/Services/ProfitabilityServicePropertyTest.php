<?php

namespace Tests\Unit\Services;

use App\Modules\Project\Models\Project;
use App\Modules\Task\Models\Task;
use App\Modules\TimeEntry\Models\TimeEntry;
use App\Modules\User\Models\User;
use App\Modules\Report\Services\ProfitabilityService;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property-Based Tests for ProfitabilityService
 *
 * Feature: monitoring-profitability-admin, Property 1: Profitability Calculation Formula Correctness
 * Feature: monitoring-profitability-admin, Property 2: Profit Decimal Precision
 * Feature: monitoring-profitability-admin, Property 3: Profit Margin Formula Correctness
 * Feature: monitoring-profitability-admin, Property 4: Time Entry Aggregation Completeness
 * Validates: Requirements 1.1, 1.2, 1.4, 1.5, 1.6, 1.7
 */
class ProfitabilityServicePropertyTest extends TestCase
{
    use RefreshDatabase, TestTrait;

    private ProfitabilityService $profitabilityService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->profitabilityService = new ProfitabilityService;
    }

    /**
     * Property 1: Profitability Calculation Formula Correctness
     *
     * For any project with time entries, the calculated profit SHALL equal:
     * Budget - Sum(TimeEntry Duration in Hours × User Internal Hourly Cost),
     * where duration in hours = duration_minutes / 60.
     *
     * **Validates: Requirements 1.1, 1.2**
     */
    public function test_profitability_calculation_formula_correctness(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(1000, 100000), // project budget
                Generator\choose(1, 5),         // number of time entries
                Generator\choose(10, 200),      // user internal hourly cost
                Generator\choose(30, 480)       // duration minutes per entry
            )
            ->then(function ($budget, $numEntries, $internalCost, $durationMinutes) {
                // Create project with specific budget
                $project = Project::factory()->create(['budget' => $budget]);

                // Create user with specific internal cost
                $user = User::factory()->create(['internal_hourly_cost' => $internalCost]);

                $totalCostExpected = 0;

                // Create time entries for the project
                for ($i = 0; $i < $numEntries; $i++) {
                    TimeEntry::factory()->create([
                        'user_id' => $user->id,
                        'trackable_type' => (new Project)->getMorphClass(),
                        'trackable_id' => $project->id,
                        'duration_minutes' => $durationMinutes,
                        'end_time' => now(), // Ensure it's completed
                    ]);

                    $totalCostExpected += ($durationMinutes / 60) * $internalCost;
                }

                // Calculate profitability
                $result = $this->profitabilityService->calculateProjectProfitability($project);

                // Verify formula: Budget - Sum(Duration Hours × Internal Cost)
                $expectedProfit = $budget - $totalCostExpected;

                $this->assertEquals(round($totalCostExpected, 2), $result['totalCost'],
                    'Total cost should equal sum of (duration_minutes / 60) × internal_hourly_cost');

                $this->assertEquals(round($expectedProfit, 2), $result['profit'],
                    'Profit should equal budget - total cost');

                $this->assertEquals($project->id, $result['projectId']);
                $this->assertEquals($project->title, $result['title']);
                $this->assertEquals(round($budget, 2), $result['budget']);
            });
    }

    /**
     * Property 2: Profit Decimal Precision
     *
     * For any profitability calculation result, the profit value SHALL be
     * a decimal with exactly 2 decimal places (rounded).
     *
     * **Validates: Requirements 1.4**
     */
    public function test_profit_decimal_precision(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(1000, 50000), // project budget
                Generator\choose(1, 3),        // number of time entries
                Generator\choose(15, 150),     // user internal hourly cost (with decimals)
                Generator\choose(37, 247)      // duration minutes (odd numbers for decimal results)
            )
            ->then(function ($budget, $numEntries, $internalCost, $durationMinutes) {
                // Add decimal precision to internal cost
                $internalCostDecimal = $internalCost + 0.33;

                $project = Project::factory()->create(['budget' => $budget]);
                $user = User::factory()->create(['internal_hourly_cost' => $internalCostDecimal]);

                // Create time entries that will result in decimal calculations
                for ($i = 0; $i < $numEntries; $i++) {
                    TimeEntry::factory()->create([
                        'user_id' => $user->id,
                        'trackable_type' => (new Project)->getMorphClass(),
                        'trackable_id' => $project->id,
                        'duration_minutes' => $durationMinutes,
                        'end_time' => now(),
                    ]);
                }

                $result = $this->profitabilityService->calculateProjectProfitability($project);

                // Verify all monetary values have exactly 2 decimal places
                $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', (string) $result['budget'],
                    'Budget should have exactly 2 decimal places');

                $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', (string) $result['totalCost'],
                    'Total cost should have exactly 2 decimal places');

                $this->assertMatchesRegularExpression('/^-?\d+\.\d{2}$/', (string) $result['profit'],
                    'Profit should have exactly 2 decimal places');

                $this->assertMatchesRegularExpression('/^-?\d+\.\d{2}$/', (string) $result['profitMargin'],
                    'Profit margin should have exactly 2 decimal places');
            });
    }

    /**
     * Property 3: Profit Margin Formula Correctness
     *
     * For any project with a non-zero budget, the profit margin percentage
     * SHALL equal: (Profit / Budget) × 100.
     *
     * **Validates: Requirements 1.5**
     */
    public function test_profit_margin_formula_correctness(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(1000, 80000), // project budget (non-zero)
                Generator\choose(1, 4),        // number of time entries
                Generator\choose(20, 180),     // user internal hourly cost
                Generator\choose(60, 360)      // duration minutes per entry
            )
            ->then(function ($budget, $numEntries, $internalCost, $durationMinutes) {
                $project = Project::factory()->create(['budget' => $budget]);
                $user = User::factory()->create(['internal_hourly_cost' => $internalCost]);

                $totalCost = 0;
                for ($i = 0; $i < $numEntries; $i++) {
                    TimeEntry::factory()->create([
                        'user_id' => $user->id,
                        'trackable_type' => (new Project)->getMorphClass(),
                        'trackable_id' => $project->id,
                        'duration_minutes' => $durationMinutes,
                        'end_time' => now(),
                    ]);

                    $totalCost += ($durationMinutes / 60) * $internalCost;
                }

                $result = $this->profitabilityService->calculateProjectProfitability($project);

                // Calculate expected profit margin: (Profit / Budget) × 100
                $profit = $budget - $totalCost;
                $expectedMargin = ($profit / $budget) * 100;

                $this->assertEquals(round($expectedMargin, 2), $result['profitMargin'],
                    'Profit margin should equal (profit / budget) × 100');

                // Verify margin is within reasonable bounds
                $this->assertGreaterThanOrEqual(-10000, $result['profitMargin'],
                    'Profit margin should not be unreasonably negative');
                $this->assertLessThanOrEqual(10000, $result['profitMargin'],
                    'Profit margin should not be unreasonably positive');
            });
    }

    /**
     * Property 4: Time Entry Aggregation Completeness
     *
     * For any project, the total cost calculation SHALL include all time entries from:
     * - Time entries directly tracked on the project (trackable_type = Project)
     * - Time entries tracked on all tasks belonging to the project (trackable_type = Task AND task.project_id = project.id)
     *
     * **Validates: Requirements 1.6, 1.7**
     */
    public function test_time_entry_aggregation_completeness(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(5000, 50000), // project budget
                Generator\choose(1, 3),        // number of direct project time entries
                Generator\choose(1, 3),        // number of tasks
                Generator\choose(1, 2),        // number of time entries per task
                Generator\choose(25, 125),     // user internal hourly cost
                Generator\choose(60, 300)      // duration minutes per entry
            )
            ->then(function ($budget, $projectEntries, $numTasks, $taskEntries, $internalCost, $durationMinutes) {
                $project = Project::factory()->create(['budget' => $budget]);
                $user = User::factory()->create(['internal_hourly_cost' => $internalCost]);

                $expectedTotalCost = 0;

                // Create direct project time entries
                for ($i = 0; $i < $projectEntries; $i++) {
                    TimeEntry::factory()->create([
                        'user_id' => $user->id,
                        'trackable_type' => (new Project)->getMorphClass(),
                        'trackable_id' => $project->id,
                        'duration_minutes' => $durationMinutes,
                        'end_time' => now(),
                    ]);

                    $expectedTotalCost += ($durationMinutes / 60) * $internalCost;
                }

                // Create tasks and their time entries
                for ($t = 0; $t < $numTasks; $t++) {
                    $task = Task::factory()->create(['project_id' => $project->id]);

                    for ($e = 0; $e < $taskEntries; $e++) {
                        TimeEntry::factory()->create([
                            'user_id' => $user->id,
                            'trackable_type' => Task::class,
                            'trackable_id' => $task->id,
                            'duration_minutes' => $durationMinutes,
                            'end_time' => now(),
                        ]);

                        $expectedTotalCost += ($durationMinutes / 60) * $internalCost;
                    }
                }

                // Create a time entry for a different project (should not be included)
                $otherProject = Project::factory()->create();
                TimeEntry::factory()->create([
                    'user_id' => $user->id,
                    'trackable_type' => (new Project)->getMorphClass(),
                    'trackable_id' => $otherProject->id,
                    'duration_minutes' => $durationMinutes,
                    'end_time' => now(),
                ]);

                $result = $this->profitabilityService->calculateProjectProfitability($project);

                $this->assertEquals(round($expectedTotalCost, 2), $result['totalCost'],
                    'Total cost should include all project and task time entries, but exclude other projects');

                // Verify the calculation includes both project and task entries
                $totalEntries = $projectEntries + ($numTasks * $taskEntries);
                $expectedCostFromFormula = $totalEntries * ($durationMinutes / 60) * $internalCost;

                $this->assertEquals(round($expectedCostFromFormula, 2), $result['totalCost'],
                    'Total cost should equal sum of all relevant time entries');
            });
    }

    /**
     * Property 1: Profitability Calculation Formula Correctness - Null Internal Cost Handling
     *
     * For any project with time entries where users have null internal_hourly_cost,
     * the system SHALL use default value (0) and calculate correctly.
     *
     * **Validates: Requirements 1.3**
     */
    public function test_profitability_calculation_with_null_internal_cost(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(2000, 40000), // project budget
                Generator\choose(1, 4),        // number of time entries
                Generator\choose(60, 480)      // duration minutes per entry
            )
            ->then(function ($budget, $numEntries, $durationMinutes) {
                $project = Project::factory()->create(['budget' => $budget]);

                // Create user with null internal_hourly_cost
                $user = User::factory()->create(['internal_hourly_cost' => null]);

                // Create time entries
                for ($i = 0; $i < $numEntries; $i++) {
                    TimeEntry::factory()->create([
                        'user_id' => $user->id,
                        'trackable_type' => (new Project)->getMorphClass(),
                        'trackable_id' => $project->id,
                        'duration_minutes' => $durationMinutes,
                        'end_time' => now(),
                    ]);
                }

                $result = $this->profitabilityService->calculateProjectProfitability($project);

                // With null internal cost (defaults to 0), total cost should be 0
                $this->assertEquals(0.0, $result['totalCost'],
                    'Total cost should be 0 when all users have null internal_hourly_cost');

                $this->assertEquals(round($budget, 2), $result['profit'],
                    'Profit should equal budget when total cost is 0');

                $this->assertEquals(100.0, $result['profitMargin'],
                    'Profit margin should be 100% when total cost is 0');
            });
    }

    /**
     * Property 2: Profit Decimal Precision - Zero Budget Edge Case
     *
     * When project budget is zero, profit margin calculation SHALL handle
     * division by zero gracefully and return 0.
     *
     * **Validates: Requirements 1.5**
     */
    public function test_profit_margin_with_zero_budget(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(1, 3),        // number of time entries
                Generator\choose(20, 100),     // user internal hourly cost
                Generator\choose(60, 240)      // duration minutes per entry
            )
            ->then(function ($numEntries, $internalCost, $durationMinutes) {
                // Create project with zero budget
                $project = Project::factory()->create(['budget' => 0]);
                $user = User::factory()->create(['internal_hourly_cost' => $internalCost]);

                for ($i = 0; $i < $numEntries; $i++) {
                    TimeEntry::factory()->create([
                        'user_id' => $user->id,
                        'trackable_type' => (new Project)->getMorphClass(),
                        'trackable_id' => $project->id,
                        'duration_minutes' => $durationMinutes,
                        'end_time' => now(),
                    ]);
                }

                $result = $this->profitabilityService->calculateProjectProfitability($project);

                $this->assertEquals(0.0, $result['budget'],
                    'Budget should be 0');

                $this->assertEquals(0.0, $result['profitMargin'],
                    'Profit margin should be 0 when budget is 0 (avoiding division by zero)');

                // Profit should be negative (0 - total cost)
                $this->assertLessThan(0, $result['profit'],
                    'Profit should be negative when budget is 0 and there are costs');
            });
    }

    /**
     * Property 4: Time Entry Aggregation Completeness - Active Time Entries Exclusion
     *
     * For any project, only completed time entries (with end_time) SHALL be
     * included in cost calculations. Active time entries (end_time = null)
     * SHALL be excluded.
     *
     * **Validates: Requirements 1.6, 1.7**
     */
    public function test_time_entry_aggregation_excludes_active_entries(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(3000, 30000), // project budget
                Generator\choose(1, 3),        // number of completed time entries
                Generator\choose(1, 3),        // number of active time entries
                Generator\choose(30, 120),     // user internal hourly cost
                Generator\choose(90, 270)      // duration minutes per entry
            )
            ->then(function ($budget, $completedEntries, $activeEntries, $internalCost, $durationMinutes) {
                $project = Project::factory()->create(['budget' => $budget]);
                $user = User::factory()->create(['internal_hourly_cost' => $internalCost]);

                $expectedTotalCost = 0;

                // Create completed time entries (should be included)
                for ($i = 0; $i < $completedEntries; $i++) {
                    TimeEntry::factory()->create([
                        'user_id' => $user->id,
                        'trackable_type' => (new Project)->getMorphClass(),
                        'trackable_id' => $project->id,
                        'duration_minutes' => $durationMinutes,
                        'end_time' => now(), // Completed
                    ]);

                    $expectedTotalCost += ($durationMinutes / 60) * $internalCost;
                }

                // Create active time entries (should be excluded)
                for ($i = 0; $i < $activeEntries; $i++) {
                    TimeEntry::factory()->create([
                        'user_id' => $user->id,
                        'trackable_type' => (new Project)->getMorphClass(),
                        'trackable_id' => $project->id,
                        'duration_minutes' => $durationMinutes,
                        'end_time' => null, // Active (not completed)
                    ]);
                }

                $result = $this->profitabilityService->calculateProjectProfitability($project);

                $this->assertEquals(round($expectedTotalCost, 2), $result['totalCost'],
                    'Total cost should only include completed time entries, excluding active ones');

                // Verify that active entries are not included by checking the count
                $totalTimeEntries = TimeEntry::where('trackable_type', (new Project)->getMorphClass())
                    ->where('trackable_id', $project->id)
                    ->count();

                $this->assertEquals($completedEntries + $activeEntries, $totalTimeEntries,
                    'Total time entries should include both completed and active');

                // But only completed entries should affect cost
                $completedTimeEntries = TimeEntry::where('trackable_type', (new Project)->getMorphClass())
                    ->where('trackable_id', $project->id)
                    ->whereNotNull('end_time')
                    ->count();

                $this->assertEquals($completedEntries, $completedTimeEntries,
                    'Only completed entries should be counted for cost calculation');
            });
    }
}
