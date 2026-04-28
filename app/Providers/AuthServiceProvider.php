<?php

namespace App\Providers;

use App\Modules\HR\Models\DeductionType;
use App\Modules\HR\Models\EmployeeDeduction;
use App\Modules\HR\Models\EmployeeReward;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Project\Models\Project;
use App\Modules\HR\Models\RewardType;
use App\Modules\HR\Models\SalaryAdjustment;
use App\Modules\Task\Models\Task;
use App\Policies\DeductionTypePolicy;
use App\Policies\EmployeeDeductionPolicy;
use App\Policies\EmployeeRewardPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\ProjectPolicy;
use App\Policies\RewardTypePolicy;
use App\Policies\SalaryAdjustmentPolicy;
use App\Policies\TaskPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Project::class => ProjectPolicy::class,
        Invoice::class => InvoicePolicy::class,
        Task::class => TaskPolicy::class,
        DeductionType::class => DeductionTypePolicy::class,
        RewardType::class => RewardTypePolicy::class,
        EmployeeDeduction::class => EmployeeDeductionPolicy::class,
        EmployeeReward::class => EmployeeRewardPolicy::class,
        SalaryAdjustment::class => SalaryAdjustmentPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
