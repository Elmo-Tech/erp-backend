<?php

namespace Tests\Feature\HR;

use App\Enums\PointTransactionType;
use App\Modules\Gamification\Models\PointTransaction;
use App\Modules\HR\Actions\EvaluateQABugPointsAction;
use App\Modules\HR\Actions\EvaluateTaskDeadlinePointsAction;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TaskPointEvaluationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('deadlineEvaluationCases')]
    public function test_deadline_evaluation_records_exactly_one_reward_or_penalty(
        int $deadlineOffsetDays,
        int $expectedPoints,
        PointTransactionType $expectedType
    ): void {
        Carbon::setTestNow('2026-07-29 12:00:00');

        $assignee = User::factory()->create();
        $task = ProjectTask::create([
            'project_id' => Project::factory()->create()->id,
            'title' => 'Deadline evaluation task',
            'status' => 'done',
            'due_date' => Carbon::now()->addDays($deadlineOffsetDays)->toDateString(),
            'assignee_id' => $assignee->id,
        ]);

        $this->actingAs(User::factory()->create());

        $this->app->make(EvaluateTaskDeadlinePointsAction::class)->execute($task);

        $this->assertDatabaseHas('point_transactions', [
            'user_id' => $assignee->id,
            'points' => $expectedPoints,
            'type' => $expectedType->value,
            'source_type' => ProjectTask::class,
            'source_id' => $task->id,
            'balance_after' => $expectedPoints,
        ]);
        $this->assertDatabaseCount('point_transactions', 1);
    }

    /**
     * @return array<string, array{int, int, PointTransactionType}>
     */
    public static function deadlineEvaluationCases(): array
    {
        return [
            'completed before deadline earns one point' => [
                1,
                1,
                PointTransactionType::TASK_ON_TIME,
            ],
            'completed after deadline loses one point' => [
                -1,
                -1,
                PointTransactionType::TASK_LATE,
            ],
        ];
    }

    public function test_qa_rejection_penalizes_assignee_and_rewards_reviewer_by_exactly_one_point(): void
    {
        $assignee = User::factory()->create();
        $reviewer = User::factory()->create();
        $task = ProjectTask::create([
            'project_id' => Project::factory()->create()->id,
            'title' => 'QA rejection task',
            'status' => 'in_progress',
            'assignee_id' => $assignee->id,
        ]);

        $this->actingAs($reviewer);

        $this->app->make(EvaluateQABugPointsAction::class)->execute($task);

        $developerTransaction = PointTransaction::where('user_id', $assignee->id)->sole();
        $reviewerTransaction = PointTransaction::where('user_id', $reviewer->id)->sole();

        $this->assertSame(-1, $developerTransaction->points);
        $this->assertSame(-1, $developerTransaction->balance_after);
        $this->assertSame(PointTransactionType::BUG_FOUND, $developerTransaction->type);

        $this->assertSame(1, $reviewerTransaction->points);
        $this->assertSame(1, $reviewerTransaction->balance_after);
        $this->assertSame(PointTransactionType::QUALITY_BONUS, $reviewerTransaction->type);
    }
}
