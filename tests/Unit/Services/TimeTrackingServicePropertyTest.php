<?php

namespace Tests\Unit\Services;

use App\Contracts\Trackable;
use App\Enums\LeadSource;
use App\Modules\Lead\Models\Lead;
use App\Modules\Quotation\Models\Quotation;
use App\Modules\TimeEntry\Models\TimeEntry;
use App\Modules\User\Models\User;
use App\Modules\Sales\Services\TimeTrackingService;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Property-Based Tests for TimeTrackingService
 *
 * Feature: sales-pricing-engine, Property 6: Polymorphic Time Tracking
 * Validates: Requirements 5.3
 */
class TimeTrackingServicePropertyTest extends TestCase
{
    use RefreshDatabase, TestTrait;

    private TimeTrackingService $timeTrackingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->timeTrackingService = new TimeTrackingService;
    }

    /**
     * Property 6: Polymorphic Time Tracking
     *
     * For any entity implementing the Trackable interface (Lead, Quotation),
     * the TimeTrackingService SHALL successfully start a timer and create a
     * TimeEntry with correct trackable_type and trackable_id.
     *
     * **Validates: Requirements 5.3**
     */
    public function test_polymorphic_time_tracking(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\elements(['lead', 'quotation']), // trackable entity type
                Generator\choose(1, 100) // random seed for data generation
            )
            ->then(function ($entityType, $seed) {
                // Create a user for the timer
                $user = User::factory()->create([
                    'name' => 'Test User '.$seed.'_'.uniqid(),
                    'email' => 'user'.$seed.'_'.uniqid().'@example.com',
                ]);

                // Create the trackable entity based on type
                $trackable = $this->createTrackableEntity($entityType, $seed);

                // Verify the entity implements Trackable interface
                $this->assertInstanceOf(Trackable::class, $trackable,
                    'Entity should implement Trackable interface');

                // Start timer for the trackable entity
                $timeEntry = $this->timeTrackingService->startTimer($user, $trackable);

                // Verify TimeEntry was created successfully
                $this->assertInstanceOf(TimeEntry::class, $timeEntry,
                    'startTimer should return a TimeEntry instance');

                // Verify polymorphic relationship is set correctly
                $this->assertEquals($trackable->getKey(), $timeEntry->trackable_id,
                    'TimeEntry should have correct trackable_id');
                $this->assertEquals(get_class($trackable), $timeEntry->trackable_type,
                    'TimeEntry should have correct trackable_type');

                // Verify user relationship is set correctly
                $this->assertEquals($user->id, $timeEntry->user_id,
                    'TimeEntry should belong to the correct user');

                // Verify timer is active (no end_time)
                $this->assertNull($timeEntry->end_time,
                    'New timer should be active (no end_time)');
                $this->assertNotNull($timeEntry->start_time,
                    'New timer should have start_time set');
                $this->assertTrue($timeEntry->isActive(),
                    'New timer should be active');

                // Verify the timer is persisted in database
                $this->assertDatabaseHas('time_entries', [
                    'user_id' => $user->id,
                    'trackable_id' => $trackable->getKey(),
                    'trackable_type' => get_class($trackable),
                    'end_time' => null,
                ]);

                // Verify polymorphic relationship works from TimeEntry side
                $retrievedTimeEntry = TimeEntry::find($timeEntry->id);
                $this->assertNotNull($retrievedTimeEntry->trackable,
                    'TimeEntry should be able to retrieve trackable entity');
                $this->assertEquals($trackable->getKey(), $retrievedTimeEntry->trackable->getKey(),
                    'Retrieved trackable should match original');
                $this->assertEquals(get_class($trackable), get_class($retrievedTimeEntry->trackable),
                    'Retrieved trackable should be same type as original');

                // Verify polymorphic relationship works from Trackable side
                $trackableTimeEntries = $trackable->timeEntries;
                $this->assertCount(1, $trackableTimeEntries,
                    'Trackable entity should have one time entry');
                $this->assertEquals($timeEntry->id, $trackableTimeEntries->first()->id,
                    'Trackable should reference the correct time entry');

                // Verify getTrackableDisplayName method works
                $displayName = $trackable->getTrackableDisplayName();
                $this->assertIsString($displayName,
                    'getTrackableDisplayName should return a string');
                $this->assertNotEmpty($displayName,
                    'getTrackableDisplayName should return non-empty string');

                // Clean up - stop the timer to avoid conflicts with next iteration
                $this->timeTrackingService->stopTimer($user);
            });
    }

    /**
     * Property 6: Polymorphic Time Tracking - Multiple Entity Types
     *
     * For any combination of different Trackable entity types, the TimeTrackingService
     * SHALL handle each type correctly and maintain proper polymorphic relationships.
     *
     * **Validates: Requirements 5.3**
     */
    public function test_polymorphic_time_tracking_multiple_entity_types(): void
    {
        $this
            ->limitTo(2)
            ->forAll(
                Generator\choose(1, 50) // random seed
            )
            ->then(function ($seed) {
                // Create a user
                $user = User::factory()->create([
                    'name' => 'Multi Test User '.$seed.'_'.uniqid(),
                    'email' => 'multiuser'.$seed.'_'.uniqid().'@example.com',
                ]);

                // Create different types of trackable entities
                $lead = $this->createTrackableEntity('lead', $seed);
                $quotation = $this->createTrackableEntity('quotation', $seed);

                // Test starting timer for Lead
                $leadTimeEntry = $this->timeTrackingService->startTimer($user, $lead);
                $this->assertEquals(Lead::class, $leadTimeEntry->trackable_type,
                    'Lead timer should have correct trackable_type');
                $this->assertEquals($lead->id, $leadTimeEntry->trackable_id,
                    'Lead timer should have correct trackable_id');

                // Stop lead timer
                $this->timeTrackingService->stopTimer($user);

                // Test starting timer for Quotation
                $quotationTimeEntry = $this->timeTrackingService->startTimer($user, $quotation);
                $this->assertEquals(Quotation::class, $quotationTimeEntry->trackable_type,
                    'Quotation timer should have correct trackable_type');
                $this->assertEquals($quotation->id, $quotationTimeEntry->trackable_id,
                    'Quotation timer should have correct trackable_id');

                // Verify both time entries exist in database with correct polymorphic data
                $this->assertDatabaseHas('time_entries', [
                    'user_id' => $user->id,
                    'trackable_id' => $lead->id,
                    'trackable_type' => Lead::class,
                ]);

                $this->assertDatabaseHas('time_entries', [
                    'user_id' => $user->id,
                    'trackable_id' => $quotation->id,
                    'trackable_type' => Quotation::class,
                ]);

                // Verify each entity can retrieve its own time entries
                $leadTimeEntries = $lead->timeEntries;
                $quotationTimeEntries = $quotation->timeEntries;

                $this->assertCount(1, $leadTimeEntries,
                    'Lead should have exactly one time entry');
                $this->assertCount(1, $quotationTimeEntries,
                    'Quotation should have exactly one time entry');

                $this->assertEquals($leadTimeEntry->id, $leadTimeEntries->first()->id,
                    'Lead should reference correct time entry');
                $this->assertEquals($quotationTimeEntry->id, $quotationTimeEntries->first()->id,
                    'Quotation should reference correct time entry');

                // Clean up
                $this->timeTrackingService->stopTimer($user);
            });
    }

    /**
     * Property 6: Polymorphic Time Tracking - Trackable Display Names
     *
     * For any Trackable entity, the getTrackableDisplayName method SHALL return
     * a meaningful string that identifies the entity.
     *
     * **Validates: Requirements 5.3**
     */
    public function test_polymorphic_time_tracking_display_names(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\elements(['lead', 'quotation']),
                Generator\choose(1, 100)
            )
            ->then(function ($entityType, $seed) {
                $trackable = $this->createTrackableEntity($entityType, $seed);
                $displayName = $trackable->getTrackableDisplayName();

                // Verify display name properties
                $this->assertIsString($displayName,
                    'Display name should be a string');
                $this->assertNotEmpty($displayName,
                    'Display name should not be empty');
                $this->assertGreaterThan(0, strlen(trim($displayName)),
                    'Display name should contain non-whitespace characters');

                // Verify display name contains entity-specific information
                if ($entityType === 'lead') {
                    $this->assertStringContainsString($trackable->name, $displayName,
                        'Lead display name should contain lead name');
                } elseif ($entityType === 'quotation') {
                    $this->assertStringContainsString((string) $trackable->id, $displayName,
                        'Quotation display name should contain quotation ID');
                    $this->assertStringContainsString($trackable->lead->name, $displayName,
                        'Quotation display name should contain lead name');
                }
            });
    }

    /**
     * Property 6: Polymorphic Time Tracking - Service Methods Integration
     *
     * For any Trackable entity, all TimeTrackingService methods SHALL work
     * correctly with the polymorphic relationship.
     *
     * **Validates: Requirements 5.3**
     */
    public function test_polymorphic_time_tracking_service_methods(): void
    {
        $this
            ->limitTo(2)
            ->forAll(
                Generator\elements(['lead', 'quotation']),
                Generator\choose(1, 50)
            )
            ->then(function ($entityType, $seed) {
                $user = User::factory()->create([
                    'name' => 'Service Test User '.$seed.'_'.uniqid(),
                    'email' => 'serviceuser'.$seed.'_'.uniqid().'@example.com',
                ]);

                $trackable = $this->createTrackableEntity($entityType, $seed);

                // Test startTimer
                $timeEntry = $this->timeTrackingService->startTimer($user, $trackable);
                $this->assertInstanceOf(TimeEntry::class, $timeEntry);

                // Test getActiveTimer
                $activeTimer = $this->timeTrackingService->getActiveTimer($user);
                $this->assertNotNull($activeTimer,
                    'getActiveTimer should return the active timer');
                $this->assertEquals($timeEntry->id, $activeTimer->id,
                    'getActiveTimer should return the correct timer');

                // Test getTimeEntriesForTrackable
                $trackableEntries = $this->timeTrackingService->getTimeEntriesForTrackable($trackable);
                $this->assertCount(1, $trackableEntries,
                    'getTimeEntriesForTrackable should return one entry');
                $this->assertEquals($timeEntry->id, $trackableEntries->first()->id,
                    'getTimeEntriesForTrackable should return the correct entry');

                // Test stopTimer
                $stoppedTimer = $this->timeTrackingService->stopTimer($user);
                $this->assertNotNull($stoppedTimer->end_time,
                    'Stopped timer should have end_time set');
                $this->assertNotNull($stoppedTimer->duration_minutes,
                    'Stopped timer should have duration_minutes calculated');

                // Test getTotalTimeForTrackable
                $totalTime = $this->timeTrackingService->getTotalTimeForTrackable($trackable);
                $this->assertGreaterThanOrEqual(0, $totalTime,
                    'Total time should be non-negative');
                $this->assertEquals($stoppedTimer->duration_minutes, $totalTime,
                    'Total time should match the stopped timer duration');

                // Verify no active timer after stopping
                $noActiveTimer = $this->timeTrackingService->getActiveTimer($user);
                $this->assertNull($noActiveTimer,
                    'getActiveTimer should return null after stopping timer');
            });
    }

    /**
     * Property 7: Timer Duration Calculation
     *
     * For any timer that is started and then stopped, the duration_minutes SHALL
     * equal the difference in minutes between end_time and start_time (rounded down).
     *
     * **Validates: Requirements 5.4, 5.5**
     */
    public function test_timer_duration_calculation(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\elements(['lead', 'quotation']), // trackable entity type
                Generator\choose(1, 300), // duration in seconds (1-300 seconds = 0-5 minutes)
                Generator\choose(1, 100) // random seed for data generation
            )
            ->then(function ($entityType, $durationSeconds, $seed) {
                // Create a user for the timer
                $user = User::factory()->create([
                    'name' => 'Duration Test User '.$seed.'_'.uniqid(),
                    'email' => 'durationuser'.$seed.'_'.uniqid().'@example.com',
                ]);

                // Create the trackable entity
                $trackable = $this->createTrackableEntity($entityType, $seed);

                // Start timer
                $timeEntry = $this->timeTrackingService->startTimer($user, $trackable);
                $startTime = $timeEntry->start_time;

                // Verify timer is active
                $this->assertNull($timeEntry->end_time,
                    'New timer should not have end_time');
                $this->assertNull($timeEntry->duration_minutes,
                    'New timer should not have duration_minutes');
                $this->assertTrue($timeEntry->isActive(),
                    'New timer should be active');

                // Simulate time passage by manually setting start_time to earlier
                $simulatedStartTime = now()->subSeconds($durationSeconds);
                $timeEntry->update(['start_time' => $simulatedStartTime]);

                // Stop timer - this should calculate duration automatically
                $stoppedTimer = $this->timeTrackingService->stopTimer($user);

                // Verify timer is stopped
                $this->assertNotNull($stoppedTimer->end_time,
                    'Stopped timer should have end_time set');
                $this->assertNotNull($stoppedTimer->duration_minutes,
                    'Stopped timer should have duration_minutes calculated');
                $this->assertFalse($stoppedTimer->isActive(),
                    'Stopped timer should not be active');

                // Calculate expected duration in minutes (rounded down)
                $actualDurationSeconds = $stoppedTimer->start_time->diffInSeconds($stoppedTimer->end_time);
                $expectedDurationMinutes = (int) floor($actualDurationSeconds / 60);

                // Verify duration calculation is correct
                $this->assertEquals($expectedDurationMinutes, $stoppedTimer->duration_minutes,
                    'Duration should equal difference in minutes between end_time and start_time (rounded down)');

                // Verify the duration is reasonable (should be close to our simulated duration)
                $this->assertGreaterThanOrEqual(0, $stoppedTimer->duration_minutes,
                    'Duration should be non-negative');

                // For durations >= 60 seconds, we should have at least 1 minute
                if ($durationSeconds >= 60) {
                    $this->assertGreaterThanOrEqual(1, $stoppedTimer->duration_minutes,
                        'Duration >= 60 seconds should result in at least 1 minute');
                }

                // For durations < 60 seconds, we should have 0 minutes (rounded down)
                if ($durationSeconds < 60) {
                    $this->assertEquals(0, $stoppedTimer->duration_minutes,
                        'Duration < 60 seconds should result in 0 minutes (rounded down)');
                }

                // Verify the calculation matches manual calculation
                $manualCalculation = (int) floor($stoppedTimer->start_time->diffInSeconds($stoppedTimer->end_time) / 60);
                $this->assertEquals($manualCalculation, $stoppedTimer->duration_minutes,
                    'Duration should match manual calculation');

                // Verify database persistence
                $this->assertDatabaseHas('time_entries', [
                    'id' => $stoppedTimer->id,
                    'user_id' => $user->id,
                    'duration_minutes' => $stoppedTimer->duration_minutes,
                ]);

                // Verify the time entry is no longer active
                $this->assertDatabaseMissing('time_entries', [
                    'id' => $stoppedTimer->id,
                    'end_time' => null,
                ]);
            });
    }

    /**
     * Property 7: Timer Duration Calculation - Edge Cases
     *
     * For any timer with various edge case durations, the duration calculation
     * SHALL handle edge cases correctly (very short durations, exact minute boundaries).
     *
     * **Validates: Requirements 5.4, 5.5**
     */
    public function test_timer_duration_calculation_edge_cases(): void
    {
        $this
            ->limitTo(2)
            ->forAll(
                Generator\elements([
                    0,      // 0 seconds - should be 0 minutes
                    1,      // 1 second - should be 0 minutes (rounded down)
                    59,     // 59 seconds - should be 0 minutes (rounded down)
                    60,     // 60 seconds - should be 1 minute exactly
                    61,     // 61 seconds - should be 1 minute (rounded down)
                    119,    // 119 seconds - should be 1 minute (rounded down)
                    120,    // 120 seconds - should be 2 minutes exactly
                    3600,   // 1 hour - should be 60 minutes
                    3661,   // 1 hour 1 minute 1 second - should be 61 minutes
                ]),
                Generator\choose(1, 50) // random seed
            )
            ->then(function ($durationSeconds, $seed) {
                // Create a user for the timer
                $user = User::factory()->create([
                    'name' => 'Edge Case User '.$seed.'_'.uniqid(),
                    'email' => 'edgeuser'.$seed.'_'.uniqid().'@example.com',
                ]);

                // Create a lead for testing
                $trackable = $this->createTrackableEntity('lead', $seed);

                // Start timer
                $timeEntry = $this->timeTrackingService->startTimer($user, $trackable);

                // Simulate specific duration by setting start_time
                $simulatedStartTime = now()->subSeconds($durationSeconds);
                $timeEntry->update(['start_time' => $simulatedStartTime]);

                // Stop timer
                $stoppedTimer = $this->timeTrackingService->stopTimer($user);

                // Calculate expected duration (rounded down to minutes)
                $expectedMinutes = (int) floor($durationSeconds / 60);

                // Verify duration calculation
                $this->assertEquals($expectedMinutes, $stoppedTimer->duration_minutes,
                    "Duration of {$durationSeconds} seconds should result in {$expectedMinutes} minutes");

                // Verify the calculation matches our floor-based calculation
                $actualDurationSeconds = $stoppedTimer->start_time->diffInSeconds($stoppedTimer->end_time);
                $expectedFlooredMinutes = (int) floor($actualDurationSeconds / 60);
                $this->assertEquals($expectedFlooredMinutes, $stoppedTimer->duration_minutes,
                    'Duration should match floor-based calculation (rounded down)');
            });
    }

    /**
     * Property 7: Timer Duration Calculation - Multiple Timers
     *
     * For any sequence of multiple timers for the same user, each timer's duration
     * SHALL be calculated independently and correctly.
     *
     * **Validates: Requirements 5.4, 5.5**
     */
    public function test_timer_duration_calculation_multiple_timers(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(2, 5), // number of timers to create
                Generator\choose(1, 30) // random seed
            )
            ->then(function ($timerCount, $seed) {
                // Create a user for the timers
                $user = User::factory()->create([
                    'name' => 'Multi Timer User '.$seed.'_'.uniqid(),
                    'email' => 'multitimeruser'.$seed.'_'.uniqid().'@example.com',
                ]);

                $createdTimers = [];
                $expectedDurations = [];

                // Create multiple timers with different durations
                for ($i = 0; $i < $timerCount; $i++) {
                    $trackable = $this->createTrackableEntity('lead', $seed + $i);
                    $durationSeconds = 60 + ($i * 30); // 60, 90, 120, 150, 180 seconds
                    $expectedMinutes = (int) floor($durationSeconds / 60);

                    // Start timer
                    $timeEntry = $this->timeTrackingService->startTimer($user, $trackable);

                    // Simulate duration
                    $simulatedStartTime = now()->subSeconds($durationSeconds);
                    $timeEntry->update(['start_time' => $simulatedStartTime]);

                    // Stop timer
                    $stoppedTimer = $this->timeTrackingService->stopTimer($user);

                    $createdTimers[] = $stoppedTimer;
                    $expectedDurations[] = $expectedMinutes;

                    // Verify this timer's duration
                    $this->assertEquals($expectedMinutes, $stoppedTimer->duration_minutes,
                        "Timer {$i} with {$durationSeconds} seconds should have {$expectedMinutes} minutes");
                }

                // Verify all timers have correct durations
                $this->assertCount($timerCount, $createdTimers,
                    'Should have created expected number of timers');

                for ($i = 0; $i < $timerCount; $i++) {
                    $timer = $createdTimers[$i];
                    $expectedDuration = $expectedDurations[$i];

                    $this->assertEquals($expectedDuration, $timer->duration_minutes,
                        "Timer {$i} should maintain correct duration");
                    $this->assertNotNull($timer->end_time,
                        "Timer {$i} should be stopped");
                    $this->assertFalse($timer->isActive(),
                        "Timer {$i} should not be active");
                }

                // Verify all timers are persisted correctly in database
                foreach ($createdTimers as $i => $timer) {
                    $this->assertDatabaseHas('time_entries', [
                        'id' => $timer->id,
                        'user_id' => $user->id,
                        'duration_minutes' => $expectedDurations[$i],
                    ]);
                }
            });
    }

    /**
     * Property 8: Single Active Timer Per User
     *
     * For any user with an active timer (end_time is null), attempting to start
     * a new timer SHALL throw a RuntimeException.
     *
     * **Validates: Requirements 5.9**
     */
    public function test_single_active_timer_per_user(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\elements(['lead', 'quotation']), // first trackable entity type
                Generator\elements(['lead', 'quotation']), // second trackable entity type
                Generator\choose(1, 100) // random seed for data generation
            )
            ->then(function ($firstEntityType, $secondEntityType, $seed) {
                // Create a user for the timer
                $user = User::factory()->create([
                    'name' => 'Single Timer User '.$seed.'_'.uniqid(),
                    'email' => 'singletimer'.$seed.'_'.uniqid().'@example.com',
                ]);

                // Create two different trackable entities
                $firstTrackable = $this->createTrackableEntity($firstEntityType, $seed);
                $secondTrackable = $this->createTrackableEntity($secondEntityType, $seed + 1000);

                // Verify user has no active timer initially
                $initialActiveTimer = $this->timeTrackingService->getActiveTimer($user);
                $this->assertNull($initialActiveTimer,
                    'User should have no active timer initially');

                // Start first timer - this should succeed
                $firstTimer = $this->timeTrackingService->startTimer($user, $firstTrackable);
                $this->assertInstanceOf(TimeEntry::class, $firstTimer,
                    'First timer should be created successfully');
                $this->assertTrue($firstTimer->isActive(),
                    'First timer should be active');

                // Verify user now has an active timer
                $activeTimer = $this->timeTrackingService->getActiveTimer($user);
                $this->assertNotNull($activeTimer,
                    'User should have an active timer after starting first timer');
                $this->assertEquals($firstTimer->id, $activeTimer->id,
                    'Active timer should be the first timer');

                // Verify the active timer is persisted correctly
                $this->assertDatabaseHas('time_entries', [
                    'id' => $firstTimer->id,
                    'user_id' => $user->id,
                    'trackable_id' => $firstTrackable->getKey(),
                    'trackable_type' => get_class($firstTrackable),
                    'end_time' => null, // Active timer has no end_time
                ]);

                // Attempt to start second timer - this should throw RuntimeException
                $exceptionThrown = false;
                $exceptionMessage = '';

                try {
                    $this->timeTrackingService->startTimer($user, $secondTrackable);
                } catch (RuntimeException $e) {
                    $exceptionThrown = true;
                    $exceptionMessage = $e->getMessage();
                }

                // Verify RuntimeException was thrown
                $this->assertTrue($exceptionThrown,
                    'Starting a second timer should throw RuntimeException');
                $this->assertStringContainsString('already has an active timer', $exceptionMessage,
                    'Exception message should indicate user already has active timer');
                $this->assertStringContainsString('Stop it before starting a new one', $exceptionMessage,
                    'Exception message should instruct to stop current timer first');

                // Verify first timer is still active and unchanged
                $stillActiveTimer = $this->timeTrackingService->getActiveTimer($user);
                $this->assertNotNull($stillActiveTimer,
                    'First timer should still be active after failed second timer attempt');
                $this->assertEquals($firstTimer->id, $stillActiveTimer->id,
                    'Active timer should still be the first timer');
                $this->assertTrue($stillActiveTimer->isActive(),
                    'First timer should still be active');

                // Verify no second timer was created in database
                $this->assertDatabaseMissing('time_entries', [
                    'user_id' => $user->id,
                    'trackable_id' => $secondTrackable->getKey(),
                    'trackable_type' => get_class($secondTrackable),
                ]);

                // Verify only one active timer exists for this user
                $activeTimersCount = TimeEntry::where('user_id', $user->id)
                    ->whereNull('end_time')
                    ->count();
                $this->assertEquals(1, $activeTimersCount,
                    'User should have exactly one active timer');

                // Stop the first timer
                $stoppedTimer = $this->timeTrackingService->stopTimer($user);
                $this->assertFalse($stoppedTimer->isActive(),
                    'Timer should not be active after stopping');

                // Verify user has no active timer after stopping
                $noActiveTimer = $this->timeTrackingService->getActiveTimer($user);
                $this->assertNull($noActiveTimer,
                    'User should have no active timer after stopping');

                // Now starting second timer should succeed
                $secondTimer = $this->timeTrackingService->startTimer($user, $secondTrackable);
                $this->assertInstanceOf(TimeEntry::class, $secondTimer,
                    'Second timer should be created successfully after stopping first');
                $this->assertTrue($secondTimer->isActive(),
                    'Second timer should be active');

                // Verify second timer is different from first
                $this->assertNotEquals($firstTimer->id, $secondTimer->id,
                    'Second timer should be a different instance');

                // Clean up
                $this->timeTrackingService->stopTimer($user);
            });
    }

    /**
     * Property 8: Single Active Timer Per User - Multiple Users
     *
     * For any multiple users, each user SHALL be able to have their own active timer
     * independently, but each individual user SHALL be limited to one active timer.
     *
     * **Validates: Requirements 5.9**
     */
    public function test_single_active_timer_per_user_multiple_users(): void
    {
        $this
            ->limitTo(2)
            ->forAll(
                Generator\choose(2, 5), // number of users to create
                Generator\choose(1, 50) // random seed
            )
            ->then(function ($userCount, $seed) {
                $users = [];
                $trackables = [];
                $timers = [];

                // Create multiple users and trackable entities
                for ($i = 0; $i < $userCount; $i++) {
                    $users[] = User::factory()->create([
                        'name' => 'Multi User '.$i.'_'.$seed.'_'.uniqid(),
                        'email' => 'multiuser'.$i.'_'.$seed.'_'.uniqid().'@example.com',
                    ]);

                    $trackables[] = $this->createTrackableEntity('lead', $seed + $i);
                }

                // Each user should be able to start their own timer
                for ($i = 0; $i < $userCount; $i++) {
                    $timer = $this->timeTrackingService->startTimer($users[$i], $trackables[$i]);
                    $timers[] = $timer;

                    $this->assertInstanceOf(TimeEntry::class, $timer,
                        "User {$i} should be able to start a timer");
                    $this->assertTrue($timer->isActive(),
                        "User {$i} timer should be active");
                }

                // Verify each user has exactly one active timer
                for ($i = 0; $i < $userCount; $i++) {
                    $activeTimer = $this->timeTrackingService->getActiveTimer($users[$i]);
                    $this->assertNotNull($activeTimer,
                        "User {$i} should have an active timer");
                    $this->assertEquals($timers[$i]->id, $activeTimer->id,
                        "User {$i} active timer should match created timer");

                    // Verify user cannot start a second timer
                    $secondTrackable = $this->createTrackableEntity('quotation', $seed + $i + 1000);
                    $exceptionThrown = false;

                    try {
                        $this->timeTrackingService->startTimer($users[$i], $secondTrackable);
                    } catch (RuntimeException $e) {
                        $exceptionThrown = true;
                    }

                    $this->assertTrue($exceptionThrown,
                        "User {$i} should not be able to start a second timer");
                }

                // Verify total active timers equals number of users
                $totalActiveTimers = TimeEntry::whereNull('end_time')->count();
                $this->assertEquals($userCount, $totalActiveTimers,
                    'Total active timers should equal number of users');

                // Stop all timers
                for ($i = 0; $i < $userCount; $i++) {
                    $stoppedTimer = $this->timeTrackingService->stopTimer($users[$i]);
                    $this->assertFalse($stoppedTimer->isActive(),
                        "User {$i} timer should be stopped");
                }

                // Verify no active timers remain
                $remainingActiveTimers = TimeEntry::whereNull('end_time')->count();
                $this->assertEquals(0, $remainingActiveTimers,
                    'No active timers should remain after stopping all');
            });
    }

    /**
     * Property 8: Single Active Timer Per User - Edge Cases
     *
     * For any user, the single active timer constraint SHALL be enforced
     * even in edge cases (same trackable entity, different trackable types).
     *
     * **Validates: Requirements 5.9**
     */
    public function test_single_active_timer_per_user_edge_cases(): void
    {
        $this
            ->limitTo(2)
            ->forAll(
                Generator\elements(['lead', 'quotation']),
                Generator\choose(1, 50)
            )
            ->then(function ($entityType, $seed) {
                // Create a user
                $user = User::factory()->create([
                    'name' => 'Edge Case User '.$seed.'_'.uniqid(),
                    'email' => 'edgecaseuser'.$seed.'_'.uniqid().'@example.com',
                ]);

                // Create a trackable entity
                $trackable = $this->createTrackableEntity($entityType, $seed);

                // Start first timer
                $firstTimer = $this->timeTrackingService->startTimer($user, $trackable);
                $this->assertTrue($firstTimer->isActive(),
                    'First timer should be active');

                // Test Case 1: Try to start timer for the SAME trackable entity
                $exceptionThrown = false;
                try {
                    $this->timeTrackingService->startTimer($user, $trackable);
                } catch (RuntimeException $e) {
                    $exceptionThrown = true;
                }
                $this->assertTrue($exceptionThrown,
                    'Should not be able to start timer for same trackable entity');

                // Test Case 2: Try to start timer for different trackable entity of same type
                $differentSameType = $this->createTrackableEntity($entityType, $seed + 100);
                $exceptionThrown = false;
                try {
                    $this->timeTrackingService->startTimer($user, $differentSameType);
                } catch (RuntimeException $e) {
                    $exceptionThrown = true;
                }
                $this->assertTrue($exceptionThrown,
                    'Should not be able to start timer for different entity of same type');

                // Test Case 3: Try to start timer for different trackable entity of different type
                $differentType = $entityType === 'lead' ? 'quotation' : 'lead';
                $differentTypeEntity = $this->createTrackableEntity($differentType, $seed + 200);
                $exceptionThrown = false;
                try {
                    $this->timeTrackingService->startTimer($user, $differentTypeEntity);
                } catch (RuntimeException $e) {
                    $exceptionThrown = true;
                }
                $this->assertTrue($exceptionThrown,
                    'Should not be able to start timer for different entity of different type');

                // Verify first timer is still the only active timer
                $activeTimer = $this->timeTrackingService->getActiveTimer($user);
                $this->assertEquals($firstTimer->id, $activeTimer->id,
                    'Original timer should still be the only active timer');

                $activeTimersCount = TimeEntry::where('user_id', $user->id)
                    ->whereNull('end_time')
                    ->count();
                $this->assertEquals(1, $activeTimersCount,
                    'Should have exactly one active timer');

                // Clean up
                $this->timeTrackingService->stopTimer($user);
            });
    }

    /**
     * Helper method to create trackable entities based on type
     */
    private function createTrackableEntity(string $type, int $seed): Trackable
    {
        switch ($type) {
            case 'lead':
                return Lead::factory()->create([
                    'name' => 'Test Lead '.$seed.'_'.uniqid(),
                    'email' => 'lead'.$seed.'_'.uniqid().'@example.com',
                    'company' => 'Test Company '.$seed,
                    'source' => LeadSource::WEBSITE,
                ]);

            case 'quotation':
                $lead = Lead::factory()->create([
                    'name' => 'Quotation Lead '.$seed.'_'.uniqid(),
                    'email' => 'quotationlead'.$seed.'_'.uniqid().'@example.com',
                    'company' => 'Quotation Company '.$seed,
                ]);

                return Quotation::factory()->create([
                    'lead_id' => $lead->id,
                    'estimations' => [
                        'frontend_hours' => 10 + ($seed % 20),
                        'backend_hours' => 15 + ($seed % 25),
                    ],
                    'hourly_rate' => 50 + ($seed % 100),
                    'discount_percentage' => $seed % 20,
                    'final_price' => 1000 + ($seed * 10),
                    'currency' => 'USD',
                ]);

            default:
                throw new \InvalidArgumentException("Unknown trackable type: {$type}");
        }
    }
}
