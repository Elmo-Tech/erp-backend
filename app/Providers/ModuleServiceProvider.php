<?php

namespace App\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerLegacyMorphAliases();

        $modulesPath = app_path('Modules');

        if (! is_dir($modulesPath)) {
            return;
        }

        foreach (File::directories($modulesPath) as $modulePath) {
            $this->loadModuleRoutes($modulePath);
            $this->loadModuleMigrations($modulePath);
            $this->loadModuleViews($modulePath);
        }
    }

    private function registerLegacyMorphAliases(): void
    {
        Relation::morphMap([
            'App\Models\User' => \App\Modules\User\Models\User::class,
            'App\Models\UserPoint' => \App\Modules\User\Models\UserPoint::class,
            'App\Models\Attendance' => \App\Modules\Attendance\Models\Attendance::class,
            'App\Models\Invoice' => \App\Modules\Invoice\Models\Invoice::class,
            'App\Models\Project' => \App\Modules\Project\Models\Project::class,
            'App\Models\Lead' => \App\Modules\Lead\Models\Lead::class,
            'App\Models\LeadActivity' => \App\Modules\Lead\Models\LeadActivity::class,
            'App\Models\LeadMeeting' => \App\Modules\Lead\Models\LeadMeeting::class,
            'App\Models\LeadHistory' => \App\Modules\Lead\Models\LeadHistory::class,
            'App\Models\Contract' => \App\Modules\Contract\Models\Contract::class,
            'App\Models\ContractMilestone' => \App\Modules\Contract\Models\ContractMilestone::class,
            'App\Models\ContractTemplate' => \App\Modules\Contract\Models\ContractTemplate::class,
            'App\Models\RecurringTaskConfig' => \App\Modules\Task\Models\RecurringTaskConfig::class,
            'App\Models\Task' => \App\Modules\Task\Models\Task::class,
            'App\Models\TaskActivity' => \App\Modules\Task\Models\TaskActivity::class,
            'App\Models\TaskAssignee' => \App\Modules\Task\Models\TaskAssignee::class,
            'App\Models\TaskChecklist' => \App\Modules\Task\Models\TaskChecklist::class,
            'App\Models\TaskChecklistItem' => \App\Modules\Task\Models\TaskChecklistItem::class,
            'App\Models\TaskDeliverable' => \App\Modules\Task\Models\TaskDeliverable::class,
            'App\Models\TaskDependency' => \App\Modules\Task\Models\TaskDependency::class,
            'App\Models\TaskLabel' => \App\Modules\Task\Models\TaskLabel::class,
            'App\Models\TaskTemplate' => \App\Modules\Task\Models\TaskTemplate::class,
            'App\Models\TaskTestScenario' => \App\Modules\Task\Models\TaskTestScenario::class,
            'App\Models\TaskTimeEstimate' => \App\Modules\Task\Models\TaskTimeEstimate::class,
            'App\Models\TimeEntry' => \App\Modules\TimeEntry\Models\TimeEntry::class,
            'App\Models\Activity' => \App\Modules\Activity\Models\Activity::class,
            'App\Models\ActivityReminder' => \App\Modules\Activity\Models\ActivityReminder::class,
            'App\Models\AuditLog' => \App\Modules\Audit\Models\AuditLog::class,
            'App\Models\ChangeRequest' => \App\Modules\ProjectHandover\Models\ChangeRequest::class,
            'App\Models\CodeReview' => \App\Modules\ProjectHandover\Models\CodeReview::class,
            'App\Models\DeductionRule' => \App\Modules\HR\Models\DeductionRule::class,
            'App\Models\DeductionType' => \App\Modules\HR\Models\DeductionType::class,
            'App\Models\EmployeeDeduction' => \App\Modules\HR\Models\EmployeeDeduction::class,
            'App\Models\EmployeeReward' => \App\Modules\HR\Models\EmployeeReward::class,
            'App\Models\EvaluationPeriod' => \App\Modules\Performance\Models\EvaluationPeriod::class,
            'App\Models\InnovationLog' => \App\Modules\ProjectHandover\Models\InnovationLog::class,
            'App\Models\PerformanceEvaluation' => \App\Modules\Performance\Models\PerformanceEvaluation::class,
            'App\Models\PointPeriod' => \App\Modules\Gamification\Models\PointPeriod::class,
            'App\Models\PointTransaction' => \App\Modules\Gamification\Models\PointTransaction::class,
            'App\Models\ProjectHandover' => \App\Modules\ProjectHandover\Models\ProjectHandover::class,
            'App\Models\Proposal' => \App\Modules\Proposal\Models\Proposal::class,
            'App\Models\ProposalTemplate' => \App\Modules\Proposal\Models\ProposalTemplate::class,
            'App\Models\Quotation' => \App\Modules\Quotation\Models\Quotation::class,
            'App\Models\QuotationItem' => \App\Modules\Quotation\Models\QuotationItem::class,
            'App\Models\QuotationMilestone' => \App\Modules\Quotation\Models\QuotationMilestone::class,
            'App\Models\RewardType' => \App\Modules\HR\Models\RewardType::class,
            'App\Models\SalaryAdjustment' => \App\Modules\HR\Models\SalaryAdjustment::class,
            'App\Models\Tag' => \App\Modules\Task\Models\Tag::class,
            \App\Modules\Lead\Models\Lead::class => \App\Modules\Lead\Models\Lead::class,
            \App\Modules\Lead\Models\LeadActivity::class => \App\Modules\Lead\Models\LeadActivity::class,
            \App\Modules\Lead\Models\LeadMeeting::class => \App\Modules\Lead\Models\LeadMeeting::class,
            \App\Modules\Lead\Models\LeadHistory::class => \App\Modules\Lead\Models\LeadHistory::class,
            \App\Modules\Contract\Models\Contract::class => \App\Modules\Contract\Models\Contract::class,
            \App\Modules\Contract\Models\ContractMilestone::class => \App\Modules\Contract\Models\ContractMilestone::class,
            \App\Modules\Contract\Models\ContractTemplate::class => \App\Modules\Contract\Models\ContractTemplate::class,
            \App\Modules\Activity\Models\Activity::class => \App\Modules\Activity\Models\Activity::class,
            \App\Modules\Activity\Models\ActivityReminder::class => \App\Modules\Activity\Models\ActivityReminder::class,
            \App\Modules\Audit\Models\AuditLog::class => \App\Modules\Audit\Models\AuditLog::class,
            \App\Modules\ProjectHandover\Models\ChangeRequest::class => \App\Modules\ProjectHandover\Models\ChangeRequest::class,
            \App\Modules\ProjectHandover\Models\CodeReview::class => \App\Modules\ProjectHandover\Models\CodeReview::class,
            \App\Modules\HR\Models\DeductionRule::class => \App\Modules\HR\Models\DeductionRule::class,
            \App\Modules\HR\Models\DeductionType::class => \App\Modules\HR\Models\DeductionType::class,
            \App\Modules\HR\Models\EmployeeDeduction::class => \App\Modules\HR\Models\EmployeeDeduction::class,
            \App\Modules\HR\Models\EmployeeReward::class => \App\Modules\HR\Models\EmployeeReward::class,
            \App\Modules\Performance\Models\EvaluationPeriod::class => \App\Modules\Performance\Models\EvaluationPeriod::class,
            \App\Modules\ProjectHandover\Models\InnovationLog::class => \App\Modules\ProjectHandover\Models\InnovationLog::class,
            \App\Modules\Performance\Models\PerformanceEvaluation::class => \App\Modules\Performance\Models\PerformanceEvaluation::class,
            \App\Modules\Gamification\Models\PointPeriod::class => \App\Modules\Gamification\Models\PointPeriod::class,
            \App\Modules\Gamification\Models\PointTransaction::class => \App\Modules\Gamification\Models\PointTransaction::class,
            \App\Modules\ProjectHandover\Models\ProjectHandover::class => \App\Modules\ProjectHandover\Models\ProjectHandover::class,
            \App\Modules\Proposal\Models\Proposal::class => \App\Modules\Proposal\Models\Proposal::class,
            \App\Modules\Proposal\Models\ProposalTemplate::class => \App\Modules\Proposal\Models\ProposalTemplate::class,
            \App\Modules\Quotation\Models\Quotation::class => \App\Modules\Quotation\Models\Quotation::class,
            \App\Modules\Quotation\Models\QuotationItem::class => \App\Modules\Quotation\Models\QuotationItem::class,
            \App\Modules\Quotation\Models\QuotationMilestone::class => \App\Modules\Quotation\Models\QuotationMilestone::class,
            \App\Modules\HR\Models\RewardType::class => \App\Modules\HR\Models\RewardType::class,
            \App\Modules\HR\Models\SalaryAdjustment::class => \App\Modules\HR\Models\SalaryAdjustment::class,
            \App\Modules\Task\Models\Tag::class => \App\Modules\Task\Models\Tag::class,
        ]);
    }

    private function loadModuleRoutes(string $modulePath): void
    {
        $routeFile = $modulePath.'/Routes/api.php';

        if (! is_file($routeFile)) {
            return;
        }

        Route::prefix('api')
            ->middleware('api')
            ->group($routeFile);
    }

    private function loadModuleMigrations(string $modulePath): void
    {
        $migrationPath = $modulePath.'/Database/Migrations';

        if (is_dir($migrationPath)) {
            $this->loadMigrationsFrom($migrationPath);
        }
    }

    private function loadModuleViews(string $modulePath): void
    {
        $viewPath = $modulePath.'/Resources/views';

        if (is_dir($viewPath)) {
            $this->loadViewsFrom($viewPath, strtolower(basename($modulePath)));
        }
    }
}
