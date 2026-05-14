<?php

namespace App\Providers;

use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Project\Models\ProjectTimeEntry;
use App\Policies\ProjectPolicy;
use App\Policies\ProjectTimeEntryPolicy;
use App\Policies\TaskPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Project::class          => ProjectPolicy::class,
        ProjectTask::class      => TaskPolicy::class,
        ProjectTimeEntry::class => ProjectTimeEntryPolicy::class,
    ];

    public function boot(): void
    {
        //
    }
}
