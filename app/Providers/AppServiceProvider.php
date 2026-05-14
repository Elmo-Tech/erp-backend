<?php

namespace App\Providers;

use App\Modules\Project\Models\Milestone;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMember;
use App\Modules\Project\Models\ProjectTask;
use App\Observers\MilestoneObserver;
use App\Observers\ProjectMemberObserver;
use App\Observers\ProjectObserver;
use App\Observers\ProjectTaskObserver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS URLs in production
        if (config('app.env') === 'production' || request()->isSecure()) {
            URL::forceScheme('https');
        }

        // Register event listeners for broadcasting
        Event::listen(NotificationCreated::class, function (NotificationCreated $event) {
            // Event will be automatically broadcast due to ShouldBroadcastNow interface
        });

        Event::listen(TimerUpdated::class, function (TimerUpdated $event) {
            // Event will be automatically broadcast due to ShouldBroadcastNow interface
        });

        // ── Audit Trail Observers ─────────────────────────────────────────────
        // These observers write to project_histories for every create/update/delete
        // event on execution-layer models, powering the Activity tab.
        Milestone::observe(MilestoneObserver::class);
        ProjectTask::observe(ProjectTaskObserver::class);
        ProjectMember::observe(ProjectMemberObserver::class);
    }
}
