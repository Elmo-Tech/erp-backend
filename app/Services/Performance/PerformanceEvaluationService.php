<?php

namespace App\Services\Performance;

use App\Enums\TaskStatus;
use App\Modules\Attendance\Models\Attendance;
use App\Models\CodeReview;
use App\Models\EvaluationPeriod;
use App\Models\InnovationLog;
use App\Models\PerformanceEvaluation;
use App\Modules\Task\Models\Task;
use App\Modules\User\Models\User;
use Illuminate\Support\Carbon;

class PerformanceEvaluationService
{
    /**
     * Score weights as per the evaluation system
     */
    private const WEIGHTS = [
        'technical_quality' => 30,  // Bug Severity (15) + Code Quality (15)
        'innovation' => 20,
        'productivity' => 25,       // On-time (15) + Flexibility (10)
        'soft_skills' => 25,        // Communication (10) + Attendance (10) + Initiative (5)
    ];

    /**
     * Create or update evaluation for a user in a period
     */
    public function createEvaluation(User $user, EvaluationPeriod $period): PerformanceEvaluation
    {
        return PerformanceEvaluation::updateOrCreate(
            [
                'user_id' => $user->id,
                'evaluation_period_id' => $period->id,
            ],
            ['status' => 'pending']
        );
    }

    /**
     * Calculate all scores for an evaluation
     */
    public function calculateScores(PerformanceEvaluation $evaluation): PerformanceEvaluation
    {
        $user = $evaluation->user;
        $period = $evaluation->period;
        $startDate = $period->start_date;
        $endDate = $period->end_date;

        // Calculate each category
        $technicalScores = $this->calculateTechnicalQuality($user, $startDate, $endDate);
        $innovationScore = $this->calculateInnovationScore($user, $startDate, $endDate);
        $productivityScores = $this->calculateProductivityScore($user, $startDate, $endDate);
        $softSkillsScores = $this->calculateSoftSkillsScore($user, $startDate, $endDate);

        // Update evaluation
        $evaluation->bug_severity_score = $technicalScores['bugSeverity'];
        $evaluation->code_quality_score = $technicalScores['codeQuality'];
        $evaluation->technical_quality_total = $technicalScores['total'];

        $evaluation->innovation_score = $innovationScore;

        $evaluation->on_time_delivery_score = $productivityScores['onTimeDelivery'];
        $evaluation->flexibility_score = $productivityScores['flexibility'];
        $evaluation->productivity_total = $productivityScores['total'];

        $evaluation->communication_score = $softSkillsScores['communication'];
        $evaluation->attendance_score = $softSkillsScores['attendance'];
        $evaluation->initiative_score = $softSkillsScores['initiative'];
        $evaluation->soft_skills_total = $softSkillsScores['total'];

        // Calculate totals
        $evaluation->calculateTotals();
        $evaluation->determineBonusTier();
        $evaluation->calculateBonusAmount();

        $evaluation->status = 'completed';
        $evaluation->save();

        return $evaluation;
    }

    /**
     * Calculate Technical Quality Score (30 points max)
     * - Bug Severity: 15 points
     * - Code Quality: 15 points
     */
    private function calculateTechnicalQuality(User $user, Carbon $startDate, Carbon $endDate): array
    {
        // Bug Severity Score (based on reopen_count)
        $tasks = Task::whereHas('assignees', fn ($q) => $q->where('user_id', $user->id))
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->whereIn('status', [TaskStatus::DONE, TaskStatus::TESTING])
            ->get();

        $totalTasks = $tasks->count();
        $totalReopens = $tasks->sum('reopen_count');

        // Calculate bug severity score
        // 0 reopens = 15, high reopens = lower score
        $bugSeverityScore = 15;
        if ($totalTasks > 0) {
            $reopenRate = $totalReopens / $totalTasks;
            $bugSeverityScore = max(0, 15 - ($reopenRate * 5));
        }

        // Code Quality Score (from code reviews)
        $codeReviews = CodeReview::where('developer_id', $user->id)
            ->whereBetween('reviewed_at', [$startDate, $endDate])
            ->where('status', 'approved')
            ->get();

        $codeQualityScore = 15;
        if ($codeReviews->count() > 0) {
            $avgReviewScore = $codeReviews->avg('average_score');
            // Convert 1-5 scale to 0-15 scale
            $codeQualityScore = ($avgReviewScore / 5) * 15;
        }

        return [
            'bugSeverity' => round($bugSeverityScore, 2),
            'codeQuality' => round($codeQualityScore, 2),
            'total' => round($bugSeverityScore + $codeQualityScore, 2),
        ];
    }

    /**
     * Calculate Innovation Score (20 points max)
     */
    private function calculateInnovationScore(User $user, Carbon $startDate, Carbon $endDate): float
    {
        $logs = InnovationLog::where('user_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('is_verified', true)
            ->get();

        if ($logs->isEmpty()) {
            return 0;
        }

        $totalPoints = 0;
        foreach ($logs as $log) {
            $basePoints = match ($log->category) {
                'ai_tool' => 4,
                'automation' => 5,
                'new_tech' => 4,
                'process_improvement' => 3,
                default => 2,
            };

            $impactMultiplier = match ($log->impact_level) {
                'high' => 1.5,
                'medium' => 1.0,
                'low' => 0.5,
                default => 1.0,
            };

            $totalPoints += $basePoints * $impactMultiplier;
        }

        // Cap at 20 points
        return min(20, round($totalPoints, 2));
    }

    /**
     * Calculate Productivity Score (25 points max)
     * - On-time Delivery: 15 points
     * - Flexibility: 10 points
     */
    private function calculateProductivityScore(User $user, Carbon $startDate, Carbon $endDate): array
    {
        // On-time Delivery Score
        $completedTasks = Task::whereHas('assignees', fn ($q) => $q->where('user_id', $user->id))
            ->where('status', TaskStatus::DONE)
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->whereNotNull('due_date')
            ->get();

        $onTimeCount = 0;
        $totalWithDeadline = $completedTasks->count();

        foreach ($completedTasks as $task) {
            if ($task->updated_at->lte($task->due_date)) {
                $onTimeCount++;
            }
        }

        $onTimeDeliveryScore = 15;
        if ($totalWithDeadline > 0) {
            $onTimeRate = $onTimeCount / $totalWithDeadline;
            $onTimeDeliveryScore = $onTimeRate * 15;
        }

        // Flexibility Score (based on task reassignments/context switches)
        // For now, give base score - can be enhanced with more data
        $flexibilityScore = 7; // Default middle score

        return [
            'onTimeDelivery' => round($onTimeDeliveryScore, 2),
            'flexibility' => round($flexibilityScore, 2),
            'total' => round($onTimeDeliveryScore + $flexibilityScore, 2),
        ];
    }

    /**
     * Calculate Soft Skills Score (25 points max)
     * - Communication: 10 points
     * - Attendance: 10 points
     * - Initiative: 5 points
     */
    private function calculateSoftSkillsScore(User $user, Carbon $startDate, Carbon $endDate): array
    {
        // Attendance Score
        $attendances = Attendance::where('user_id', $user->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $totalDays = $attendances->count();
        $lateDays = $attendances->where('late_minutes', '>', 30)->count();
        $graceDaysUsed = $attendances->where('used_grace_day', true)->count();

        $attendanceScore = 10;
        if ($totalDays > 0) {
            // Deduct for late days (excluding grace days)
            $effectiveLateDays = max(0, $lateDays - $graceDaysUsed);
            $lateRate = $effectiveLateDays / $totalDays;
            $attendanceScore = max(0, 10 - ($lateRate * 20));
        }

        // Communication Score - default middle score (can be enhanced with feedback system)
        $communicationScore = 7;

        // Initiative Score - default middle score
        $initiativeScore = 3;

        return [
            'communication' => round($communicationScore, 2),
            'attendance' => round($attendanceScore, 2),
            'initiative' => round($initiativeScore, 2),
            'total' => round($communicationScore + $attendanceScore + $initiativeScore, 2),
        ];
    }

    /**
     * Get evaluation summary for a user
     */
    public function getEvaluationSummary(PerformanceEvaluation $evaluation): array
    {
        return [
            'userId' => $evaluation->user_id,
            'periodId' => $evaluation->evaluation_period_id,
            'periodName' => $evaluation->period->name,
            'scores' => [
                'technicalQuality' => [
                    'bugSeverity' => $evaluation->bug_severity_score,
                    'codeQuality' => $evaluation->code_quality_score,
                    'total' => $evaluation->technical_quality_total,
                    'maxScore' => 30,
                ],
                'innovation' => [
                    'score' => $evaluation->innovation_score,
                    'maxScore' => 20,
                ],
                'productivity' => [
                    'onTimeDelivery' => $evaluation->on_time_delivery_score,
                    'flexibility' => $evaluation->flexibility_score,
                    'total' => $evaluation->productivity_total,
                    'maxScore' => 25,
                ],
                'softSkills' => [
                    'communication' => $evaluation->communication_score,
                    'attendance' => $evaluation->attendance_score,
                    'initiative' => $evaluation->initiative_score,
                    'total' => $evaluation->soft_skills_total,
                    'maxScore' => 25,
                ],
            ],
            'totalScore' => $evaluation->total_score,
            'maxTotalScore' => 100,
            'bonusTier' => $evaluation->bonus_tier,
            'bonusPercentage' => $evaluation->bonus_percentage,
            'bonusAmount' => $evaluation->bonus_amount,
            'status' => $evaluation->status,
        ];
    }

    /**
     * Approve an evaluation
     */
    public function approveEvaluation(PerformanceEvaluation $evaluation, User $approver, ?string $notes = null): PerformanceEvaluation
    {
        $evaluation->update([
            'status' => 'approved',
            'evaluated_by' => $approver->id,
            'evaluated_at' => now(),
            'evaluator_notes' => $notes,
        ]);

        return $evaluation;
    }
}
