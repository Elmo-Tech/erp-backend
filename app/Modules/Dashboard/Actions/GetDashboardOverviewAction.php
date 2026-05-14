<?php

namespace App\Modules\Dashboard\Actions;

use App\Modules\Settings\Models\User;

class GetDashboardOverviewAction
{
    public function __construct(
        private GetFinancialStatsAction $financialStats,
        private GetProjectTimelineAction $projectTimeline,
        private GetTeamStatsAction $teamStats,
        private GetPersonalTasksAction $personalTasks,
        private GetPersonalBlockersAction $personalBlockers,
        private GetEscalatedBlockersAction $escalatedBlockers,
        private GetTeamPulseAction $teamPulse
    ) {}

    public function execute(User $user): array
    {
        $dashboardData = [];
        $canViewGlobalStats = $user->can('view_global_stats');

        if ($canViewGlobalStats && $user->can('view_financial_stats')) {
            $dashboardData['financial'] = $this->financialStats->execute();
        }

        if ($canViewGlobalStats && $user->can('view_project_timeline')) {
            $dashboardData['timeline'] = $this->projectTimeline->execute();
        }

        if ($canViewGlobalStats && $user->can('view_team_performance')) {
            $dashboardData['team'] = $this->teamStats->execute();
        }

        if ($user->can('view_personal_tasks')) {
            $dashboardData['personal_tasks'] = $this->personalTasks->execute($user);
            $dashboardData['blockers'] = $this->personalBlockers->execute($user);
        }

        if ($canViewGlobalStats && $user->can('view_all_blockers')) {
            $dashboardData['blockers'] = $this->personalBlockers->executeForAll();
        }

        if ($canViewGlobalStats && $user->can('view_escalated_blockers')) {
            $dashboardData['escalated_blockers'] = $this->escalatedBlockers->execute();
        }

        if ($canViewGlobalStats && $user->can('view_team_pulse')) {
            $dashboardData['team_pulse'] = $this->teamPulse->execute();
        }

        return $dashboardData;
    }
}
