<?php

namespace Tests\Unit\Services;

use App\Modules\Project\Enums\ProjectStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectHistory;
use App\Modules\Settings\Models\User;
use App\Modules\Project\Actions\ProjectHistoryAction;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property-Based Tests for ProjectHistoryAction
 *
 * Feature: project-management-wiki, Property 7: History Records Created on Update
 * Validates: Requirements 3.2, 9.2, 9.3
 */
class ProjectHistoryServicePropertyTest extends TestCase
{
    use RefreshDatabase, TestTrait;

    private ProjectHistoryAction $historyService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->historyService = new ProjectHistoryAction;
    }

    /**
     * Property 7: History Records Created on Update
     *
     * For any project field update, a corresponding ProjectHistory record SHALL be
     * created with the correct old_value, new_value, and changed_by fields.
     *
     * **Validates: Requirements 3.2, 9.2, 9.3**
     */
    public function test_history_records_created_on_update(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\elements(['title', 'description', 'status', 'budget', 'deadline']), // field names
                Generator\choose(1, 100) // random seed for data generation
            )
            ->then(function ($fieldName, $seed) {
                // Create a user who will make the change
                $user = User::factory()->create([
                    'name' => 'History Test User '.$seed.'_'.uniqid(),
                    'email' => 'historyuser'.$seed.'_'.uniqid().'@example.com',
                ]);

                // Authenticate the user
                $this->actingAs($user);
                $client = $this->ensureClientProfile($user);

                // Create a project
                $project = Project::factory()->create([
                    'client_id' => $client->id,
                    'title' => 'Original Title '.$seed,
                    'description' => 'Original Description '.$seed,
                    'status' => ProjectStatus::DRAFT,
                    'budget' => 10000.00 + $seed,
                    'deadline' => now()->addDays(30 + $seed),
                ]);

                // Generate old and new values based on field type
                $oldValue = $this->getOldValue($project, $fieldName);
                $newValue = $this->generateNewValue($fieldName, $seed);
                $reason = $this->historyService->requiresReason($fieldName)
                    ? 'Test reason for change '.$seed
                    : null;

                // Record the change using the service
                $historyRecord = $this->historyService->recordChange(
                    $project,
                    $fieldName,
                    $oldValue,
                    $newValue,
                    $reason
                );

                // Verify history record was created
                $this->assertInstanceOf(ProjectHistory::class, $historyRecord,
                    'recordChange should return a ProjectHistory instance');

                // Verify the history record has correct project_id
                $this->assertEquals($project->id, $historyRecord->project_id,
                    'History record should reference the correct project');

                // Verify the history record has correct field_name
                $this->assertEquals($fieldName, $historyRecord->field_name,
                    'History record should have correct field_name');

                // Verify the history record has correct old_value
                $expectedOldValue = is_null($oldValue) ? null : (string) $oldValue;
                $this->assertEquals($expectedOldValue, $historyRecord->old_value,
                    'History record should have correct old_value');

                // Verify the history record has correct new_value
                $expectedNewValue = is_null($newValue) ? null : (string) $newValue;
                $this->assertEquals($expectedNewValue, $historyRecord->new_value,
                    'History record should have correct new_value');

                // Verify the history record has correct changed_by (authenticated user)
                $this->assertEquals($user->id, $historyRecord->changed_by,
                    'History record should have correct changed_by user');

                // Verify the history record has correct reason
                $this->assertEquals($reason, $historyRecord->reason,
                    'History record should have correct reason');

                // Verify the record is persisted in database
                $this->assertDatabaseHas('project_histories', [
                    'id' => $historyRecord->id,
                    'project_id' => $project->id,
                    'field_name' => $fieldName,
                    'old_value' => $expectedOldValue,
                    'new_value' => $expectedNewValue,
                    'changed_by' => $user->id,
                ]);

                // Verify the relationship works from ProjectHistory side
                $retrievedHistory = ProjectHistory::find($historyRecord->id);
                $this->assertNotNull($retrievedHistory->project,
                    'History record should be able to retrieve project');
                $this->assertEquals($project->id, $retrievedHistory->project->id,
                    'Retrieved project should match original');

                // Verify the changedByUser relationship works
                $this->assertNotNull($retrievedHistory->changedByUser,
                    'History record should be able to retrieve changed_by user');
                $this->assertEquals($user->id, $retrievedHistory->changedByUser->id,
                    'Retrieved user should match the one who made the change');

                // Verify the relationship works from Project side
                $projectHistories = $project->histories;
                $this->assertGreaterThanOrEqual(1, $projectHistories->count(),
                    'Project should have at least one history record');
                $this->assertTrue($projectHistories->contains('id', $historyRecord->id),
                    'Project histories should contain the created record');
            });
    }

    /**
     * Property 7: History Records Created on Update - Multiple Changes
     *
     * For any sequence of multiple field updates on the same project, each update
     * SHALL create a separate history record with correct values.
     *
     * **Validates: Requirements 3.2, 9.2, 9.3**
     */
    public function test_history_records_multiple_changes(): void
    {
        $this
            ->limitTo(2)
            ->forAll(
                Generator\choose(2, 5), // number of changes to make
                Generator\choose(1, 50) // random seed
            )
            ->then(function ($changeCount, $seed) {
                // Create a user who will make the changes
                $user = User::factory()->create([
                    'name' => 'Multi Change User '.$seed.'_'.uniqid(),
                    'email' => 'multichange'.$seed.'_'.uniqid().'@example.com',
                ]);

                // Authenticate the user
                $this->actingAs($user);
                $client = $this->ensureClientProfile($user);

                // Create a project
                $project = Project::factory()->create([
                    'client_id' => $client->id,
                ]);

                $createdHistories = [];
                $fields = ['title', 'description', 'status', 'budget', 'deadline'];

                // Make multiple changes
                for ($i = 0; $i < $changeCount; $i++) {
                    $fieldName = $fields[$i % count($fields)];
                    $oldValue = 'Old Value '.$i;
                    $newValue = 'New Value '.$i.'_'.$seed;
                    $reason = $this->historyService->requiresReason($fieldName)
                        ? 'Reason for change '.$i
                        : null;

                    $historyRecord = $this->historyService->recordChange(
                        $project,
                        $fieldName,
                        $oldValue,
                        $newValue,
                        $reason
                    );

                    $createdHistories[] = [
                        'record' => $historyRecord,
                        'fieldName' => $fieldName,
                        'oldValue' => $oldValue,
                        'newValue' => $newValue,
                        'reason' => $reason,
                    ];
                }

                // Verify all history records were created
                $this->assertCount($changeCount, $createdHistories,
                    'Should have created expected number of history records');

                // Verify each history record has correct values
                foreach ($createdHistories as $index => $historyData) {
                    $record = $historyData['record'];

                    $this->assertEquals($project->id, $record->project_id,
                        "History record {$index} should reference correct project");
                    $this->assertEquals($historyData['fieldName'], $record->field_name,
                        "History record {$index} should have correct field_name");
                    $this->assertEquals($historyData['oldValue'], $record->old_value,
                        "History record {$index} should have correct old_value");
                    $this->assertEquals($historyData['newValue'], $record->new_value,
                        "History record {$index} should have correct new_value");
                    $this->assertEquals($user->id, $record->changed_by,
                        "History record {$index} should have correct changed_by");
                }

                // Verify all records are in the project's history
                $projectHistories = $project->fresh()->histories;
                $this->assertGreaterThanOrEqual($changeCount, $projectHistories->count(),
                    'Project should have at least the expected number of history records');

                // Verify each created record is in the project's history
                foreach ($createdHistories as $historyData) {
                    $this->assertTrue(
                        $projectHistories->contains('id', $historyData['record']->id),
                        'Project histories should contain all created records'
                    );
                }
            });
    }

    /**
     * Property 7: History Records Created on Update - Null Values
     *
     * For any field update where old_value or new_value is null, the history record
     * SHALL correctly store null values.
     *
     * **Validates: Requirements 3.2, 9.2, 9.3**
     */
    public function test_history_records_null_values(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\elements(['description', 'deadline', 'quotation_id']), // nullable fields
                Generator\bool(), // whether old_value is null
                Generator\bool(), // whether new_value is null
                Generator\choose(1, 100) // random seed
            )
            ->then(function ($fieldName, $oldIsNull, $newIsNull, $seed) {
                // Create a user
                $user = User::factory()->create([
                    'name' => 'Null Test User '.$seed.'_'.uniqid(),
                    'email' => 'nulltest'.$seed.'_'.uniqid().'@example.com',
                ]);

                $this->actingAs($user);
                $client = $this->ensureClientProfile($user);

                // Create a project
                $project = Project::factory()->create([
                    'client_id' => $client->id,
                ]);

                // Generate values based on null flags
                $oldValue = $oldIsNull ? null : 'Old Value '.$seed;
                $newValue = $newIsNull ? null : 'New Value '.$seed;

                // Record the change
                $historyRecord = $this->historyService->recordChange(
                    $project,
                    $fieldName,
                    $oldValue,
                    $newValue
                );

                // Verify null handling
                if ($oldIsNull) {
                    $this->assertNull($historyRecord->old_value,
                        'History record should store null for old_value when input is null');
                } else {
                    $this->assertEquals((string) $oldValue, $historyRecord->old_value,
                        'History record should store string value for old_value');
                }

                if ($newIsNull) {
                    $this->assertNull($historyRecord->new_value,
                        'History record should store null for new_value when input is null');
                } else {
                    $this->assertEquals((string) $newValue, $historyRecord->new_value,
                        'History record should store string value for new_value');
                }

                // Verify database persistence
                $this->assertDatabaseHas('project_histories', [
                    'id' => $historyRecord->id,
                    'old_value' => $oldIsNull ? null : (string) $oldValue,
                    'new_value' => $newIsNull ? null : (string) $newValue,
                ]);
            });
    }

    /**
     * Property 7: History Records Created on Update - Value Type Conversion
     *
     * For any field update with non-string values (integers, floats, enums),
     * the history record SHALL convert values to strings correctly.
     *
     * **Validates: Requirements 3.2, 9.2, 9.3**
     */
    public function test_history_records_value_type_conversion(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(1000, 100000), // budget value (integer)
                Generator\choose(1, 99), // decimal part
                Generator\choose(1, 100) // random seed
            )
            ->then(function ($budgetInt, $decimalPart, $seed) {
                // Create a user
                $user = User::factory()->create([
                    'name' => 'Type Conv User '.$seed.'_'.uniqid(),
                    'email' => 'typeconv'.$seed.'_'.uniqid().'@example.com',
                ]);

                $this->actingAs($user);
                $client = $this->ensureClientProfile($user);

                // Create a project
                $project = Project::factory()->create([
                    'client_id' => $client->id,
                ]);

                // Test with decimal budget value
                $oldBudget = $budgetInt + ($decimalPart / 100);
                $newBudget = $oldBudget + 1000.50;

                $historyRecord = $this->historyService->recordChange(
                    $project,
                    'budget',
                    $oldBudget,
                    $newBudget,
                    'Budget adjustment test'
                );

                // Verify values are converted to strings
                $this->assertIsString($historyRecord->old_value,
                    'old_value should be stored as string');
                $this->assertIsString($historyRecord->new_value,
                    'new_value should be stored as string');

                // Verify the string conversion is correct
                $this->assertEquals((string) $oldBudget, $historyRecord->old_value,
                    'old_value should match string conversion of original value');
                $this->assertEquals((string) $newBudget, $historyRecord->new_value,
                    'new_value should match string conversion of original value');

                // Test with enum value (status)
                $oldStatus = ProjectStatus::DRAFT;
                $newStatus = ProjectStatus::ACTIVE;

                $statusHistoryRecord = $this->historyService->recordChange(
                    $project,
                    'status',
                    $oldStatus->value,
                    $newStatus->value
                );

                $this->assertEquals('draft', $statusHistoryRecord->old_value,
                    'Enum value should be stored as its string value');
                $this->assertEquals('active', $statusHistoryRecord->new_value,
                    'Enum value should be stored as its string value');
            });
    }

    /**
     * Property 7: History Records Created on Update - getHistory Retrieval
     *
     * For any project with history records, getHistory SHALL return all records
     * in descending order by created_at with changedByUser relationship loaded.
     *
     * **Validates: Requirements 3.2, 9.2, 9.3**
     */
    public function test_history_records_retrieval(): void
    {
        $this
            ->limitTo(2)
            ->forAll(
                Generator\choose(3, 10), // number of history records to create
                Generator\choose(1, 50) // random seed
            )
            ->then(function ($recordCount, $seed) {
                // Create a user
                $user = User::factory()->create([
                    'name' => 'Retrieval User '.$seed.'_'.uniqid(),
                    'email' => 'retrieval'.$seed.'_'.uniqid().'@example.com',
                ]);

                $this->actingAs($user);
                $client = $this->ensureClientProfile($user);

                // Create a project
                $project = Project::factory()->create([
                    'client_id' => $client->id,
                ]);

                // Create multiple history records with slight time differences
                $createdRecords = [];
                for ($i = 0; $i < $recordCount; $i++) {
                    $record = $this->historyService->recordChange(
                        $project,
                        'title',
                        'Old Title '.$i,
                        'New Title '.$i
                    );
                    $createdRecords[] = $record;

                    // Small delay to ensure different timestamps
                    usleep(1000);
                }

                // Retrieve history using the service
                $retrievedHistory = $this->historyService->getHistory($project);

                // Verify count
                $this->assertCount($recordCount, $retrievedHistory,
                    'getHistory should return all created records');

                // Verify ordering (newest first)
                $previousTimestamp = null;
                foreach ($retrievedHistory as $record) {
                    if ($previousTimestamp !== null) {
                        $this->assertGreaterThanOrEqual(
                            $record->created_at,
                            $previousTimestamp,
                            'Records should be ordered by created_at descending (newest first)'
                        );
                    }
                    $previousTimestamp = $record->created_at;
                }

                // Verify changedByUser relationship is loaded
                foreach ($retrievedHistory as $record) {
                    $this->assertTrue($record->relationLoaded('changedByUser'),
                        'changedByUser relationship should be eager loaded');
                    $this->assertNotNull($record->changedByUser,
                        'changedByUser should not be null');
                    $this->assertEquals($user->id, $record->changedByUser->id,
                        'changedByUser should be the correct user');
                }
            });
    }

    /**
     * Helper method to get the old value from a project based on field name.
     */
    private function getOldValue(Project $project, string $fieldName): mixed
    {
        return match ($fieldName) {
            'title' => $project->title,
            'description' => $project->description,
            'status' => $project->status->value,
            'budget' => $project->budget,
            'deadline' => $project->deadline?->format('Y-m-d'),
            default => null,
        };
    }

    /**
     * Helper method to generate a new value based on field name.
     */
    private function generateNewValue(string $fieldName, int $seed): mixed
    {
        return match ($fieldName) {
            'title' => 'Updated Title '.$seed,
            'description' => 'Updated Description '.$seed,
            'status' => ProjectStatus::ACTIVE->value,
            'budget' => 20000.00 + $seed,
            'deadline' => now()->addDays(60 + $seed)->format('Y-m-d'),
            default => 'New Value '.$seed,
        };
    }
}
