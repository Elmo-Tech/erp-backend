<?php

namespace App\Modules\ProjectHandover\Services;

use App\Modules\ProjectHandover\Models\InnovationLog;
use App\Modules\User\Models\User;
use Illuminate\Support\Carbon;

class InnovationTrackingService
{
    /**
     * Log a new innovation entry
     */
    public function logInnovation(User $user, array $data): InnovationLog
    {
        return InnovationLog::create([
            'user_id' => $user->id,
            'project_id' => $data['projectId'] ?? null,
            'task_id' => $data['taskId'] ?? null,
            'tool_name' => $data['toolName'],
            'category' => $data['category'],
            'description' => $data['description'],
            'impact_description' => $data['impactDescription'] ?? null,
            'impact_level' => $data['impactLevel'] ?? 'medium',
            'time_saved_minutes' => $data['timeSavedMinutes'] ?? null,
        ]);
    }

    /**
     * Verify an innovation log
     */
    public function verifyInnovation(InnovationLog $log, User $verifier): InnovationLog
    {
        $log->update([
            'is_verified' => true,
            'verified_by' => $verifier->id,
            'verified_at' => now(),
        ]);

        return $log;
    }

    /**
     * Reject/unverify an innovation log
     */
    public function rejectInnovation(InnovationLog $log): InnovationLog
    {
        $log->update([
            'is_verified' => false,
            'verified_by' => null,
            'verified_at' => null,
        ]);

        return $log;
    }

    /**
     * Get user's innovation logs for a period
     */
    public function getUserInnovations(User $user, Carbon $startDate, Carbon $endDate): array
    {
        $logs = InnovationLog::where('user_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderByDesc('created_at')
            ->get();

        return [
            'logs' => $logs,
            'summary' => [
                'total' => $logs->count(),
                'verified' => $logs->where('is_verified', true)->count(),
                'pending' => $logs->where('is_verified', false)->count(),
                'byCategory' => [
                    'aiTool' => $logs->where('category', 'ai_tool')->count(),
                    'automation' => $logs->where('category', 'automation')->count(),
                    'newTech' => $logs->where('category', 'new_tech')->count(),
                    'processImprovement' => $logs->where('category', 'process_improvement')->count(),
                ],
                'totalTimeSaved' => $logs->sum('time_saved_minutes'),
            ],
        ];
    }

    /**
     * Get pending innovations for verification
     */
    public function getPendingInnovations(): \Illuminate\Database\Eloquent\Collection
    {
        return InnovationLog::where('is_verified', false)
            ->with(['user', 'project', 'task'])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Calculate innovation score for a user in a period
     */
    public function calculateInnovationScore(User $user, Carbon $startDate, Carbon $endDate): float
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

        // Cap at 20 points (max innovation score)
        return min(20, round($totalPoints, 2));
    }
}
