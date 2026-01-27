<?php

namespace Tests\Feature;

use App\Enums\DeliverableStatus;
use App\Enums\PointTransactionType;
use App\Enums\TaskType;
use App\Models\Client;
use App\Models\PointTransaction;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDeliverable;
use App\Models\User;
use App\Notifications\DeliverableReviewed;
use App\Notifications\DeliverableSubmitted;
use App\Services\Tasks\DeliverableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DeliverablePointsAndNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private DeliverableService $deliverableService;
    private User $developer;
    private User $reviewer;
    private Task $task;
    private TaskDeliverable $deliverable;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->deliverableService = app(DeliverableService::class);
        
        // Create test users
        $this->developer = User::factory()->create(['name' => 'Test Developer']);
        $this->reviewer = User::factory()->create(['name' => 'Test Reviewer']);
        
        // Create admin role and assign to reviewer so they can review deliverables
        $adminRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin']);
        $this->reviewer->assignRole($adminRole);
        
        // Create a client first
        $client = Client::factory()->create();
        
        // Create a project with the client
        $project = Project::factory()->create(['client_id' => $client->id]);
        
        // Create a weekly task (only weekly tasks can have deliverables)
        $this->task = Task::factory()->create([
            'type' => TaskType::WEEKLY,
            'title' => 'Test Weekly Task',
            'project_id' => $project->id,
        ]);
        
        // Assign developer to task
        $this->task->assignees()->create([
            'user_id' => $this->developer->id,
            'role' => 'developer',
            'is_primary' => true,
        ]);
        
        // Create deliverable
        $this->deliverable = TaskDeliverable::factory()->create([
            'task_id' => $this->task->id,
            'title' => 'Test Deliverable',
            'status' => DeliverableStatus::PENDING,
        ]);
    }

    /** @test */
    public function it_sends_notification_when_deliverable_is_submitted()
    {
        Notification::fake();
        
        $this->actingAs($this->developer);
        
        // Submit deliverable
        $this->deliverableService->submitDeliverable(
            $this->deliverable,
            $this->developer,
            ['submissionNotes' => 'Test submission notes']
        );
        
        // Assert notification was sent to reviewers
        Notification::assertSentTo(
            [$this->reviewer],
            DeliverableSubmitted::class,
            function ($notification) {
                return $notification->deliverable->id === $this->deliverable->id &&
                       $notification->developerName === $this->developer->name;
            }
        );
    }

    /** @test */
    public function it_awards_points_when_deliverable_is_approved()
    {
        // First submit the deliverable
        $this->deliverable->update([
            'status' => DeliverableStatus::SUBMITTED,
            'submitted_at' => now(),
        ]);
        
        $this->actingAs($this->reviewer);
        
        $pointsBefore = $this->developer->total_points ?? 0;
        
        // Approve deliverable
        $this->deliverableService->approveDeliverable(
            $this->deliverable,
            $this->reviewer,
            [
                'reviewNotes' => 'Great work!',
                'pointsAwarded' => 5,
            ]
        );
        
        // Assert points were awarded
        $this->developer->refresh();
        $this->assertEquals($pointsBefore + 5, $this->developer->total_points);
        
        // Assert point transaction was created
        $this->assertDatabaseHas('point_transactions', [
            'user_id' => $this->developer->id,
            'points' => 5,
            'type' => PointTransactionType::DELIVERABLE_APPROVED->value,
            'source_type' => TaskDeliverable::class,
            'source_id' => $this->deliverable->id,
        ]);
    }

    /** @test */
    public function it_deducts_points_when_deliverable_is_rejected()
    {
        // First submit the deliverable
        $this->deliverable->update([
            'status' => DeliverableStatus::SUBMITTED,
            'submitted_at' => now(),
        ]);
        
        $this->actingAs($this->reviewer);
        
        // Give developer some points first
        $this->developer->update(['total_points' => 10]);
        $pointsBefore = $this->developer->total_points;
        
        // Reject deliverable
        $this->deliverableService->rejectDeliverable(
            $this->deliverable,
            $this->reviewer,
            [
                'reviewNotes' => 'Needs improvement',
                'pointsDeducted' => 2,
            ]
        );
        
        // Assert points were deducted
        $this->developer->refresh();
        $this->assertEquals($pointsBefore - 2, $this->developer->total_points);
        
        // Assert point transaction was created
        $this->assertDatabaseHas('point_transactions', [
            'user_id' => $this->developer->id,
            'points' => -2,
            'type' => PointTransactionType::DELIVERABLE_REJECTED->value,
            'source_type' => TaskDeliverable::class,
            'source_id' => $this->deliverable->id,
        ]);
    }

    /** @test */
    public function it_sends_notification_when_deliverable_is_reviewed()
    {
        Notification::fake();
        
        // First submit the deliverable
        $this->deliverable->update([
            'status' => DeliverableStatus::SUBMITTED,
            'submitted_at' => now(),
        ]);
        
        $this->actingAs($this->reviewer);
        
        // Approve deliverable
        $this->deliverableService->approveDeliverable(
            $this->deliverable,
            $this->reviewer,
            [
                'reviewNotes' => 'Excellent work!',
                'pointsAwarded' => 3,
            ]
        );
        
        // Assert notification was sent to developer
        Notification::assertSentTo(
            [$this->developer],
            DeliverableReviewed::class,
            function ($notification) {
                return $notification->deliverable->id === $this->deliverable->id &&
                       $notification->reviewerName === $this->reviewer->name &&
                       $notification->status === 'approved';
            }
        );
    }

    /** @test */
    public function it_creates_both_point_transaction_and_user_point_records()
    {
        // First submit the deliverable
        $this->deliverable->update([
            'status' => DeliverableStatus::SUBMITTED,
            'submitted_at' => now(),
        ]);
        
        $this->actingAs($this->reviewer);
        
        // Approve deliverable
        $this->deliverableService->approveDeliverable(
            $this->deliverable,
            $this->reviewer,
            [
                'reviewNotes' => 'Great work!',
                'pointsAwarded' => 4,
            ]
        );
        
        // Assert PointTransaction was created
        $this->assertDatabaseHas('point_transactions', [
            'user_id' => $this->developer->id,
            'points' => 4,
            'type' => PointTransactionType::DELIVERABLE_APPROVED->value,
        ]);
        
        // Assert UserPoint was created for backward compatibility
        $this->assertDatabaseHas('user_points', [
            'user_id' => $this->developer->id,
            'points_change' => 4,
            'type' => 'automatic',
            'transaction_type' => PointTransactionType::DELIVERABLE_APPROVED->value,
        ]);
    }

    /** @test */
    public function it_prevents_non_developers_from_submitting_deliverables()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only assigned developers can submit deliverables.');
        
        $this->actingAs($this->reviewer);
        
        // Try to submit deliverable as non-developer
        $this->deliverableService->submitDeliverable(
            $this->deliverable,
            $this->reviewer,
            ['submissionNotes' => 'Test submission notes']
        );
    }

    /** @test */
    public function it_prevents_reviewing_non_submitted_deliverables()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Deliverable cannot be reviewed in its current state.');
        
        $this->actingAs($this->reviewer);
        
        // Try to review pending deliverable
        $this->deliverableService->approveDeliverable(
            $this->deliverable,
            $this->reviewer,
            ['reviewNotes' => 'Test review']
        );
    }
}