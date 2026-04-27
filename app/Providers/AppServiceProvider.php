<?php

namespace App\Providers;

use App\Modules\Notification\Events\NotificationCreated;
use App\Events\TimerUpdated;
use App\Models\CodeReview;
use App\Models\InnovationLog;
use App\Modules\Project\Models\Project;
use App\Modules\Task\Models\Task;
use App\Modules\Task\Models\TaskDeliverable;
use App\Modules\Task\Models\TaskTestScenario;
use App\Modules\TimeEntry\Models\TimeEntry;
use App\Observers\CodeReviewObserver;
use App\Observers\InnovationLogObserver;
use App\Observers\ProjectObserver;
use App\Observers\TaskDeliverableObserver;
use App\Observers\TaskObserver;
use App\Observers\TaskTestScenarioObserver;
use App\Observers\TimeEntryObserver;
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

        // Register model observers
        Project::observe(ProjectObserver::class);
        Task::observe(TaskObserver::class);
        TaskTestScenario::observe(TaskTestScenarioObserver::class);
        TimeEntry::observe(TimeEntryObserver::class);
        InnovationLog::observe(InnovationLogObserver::class);
        CodeReview::observe(CodeReviewObserver::class);
        TaskDeliverable::observe(TaskDeliverableObserver::class);

        // Register event listeners for broadcasting
        Event::listen(NotificationCreated::class, function (NotificationCreated $event) {
            // Event will be automatically broadcast due to ShouldBroadcastNow interface
        });

        Event::listen(TimerUpdated::class, function (TimerUpdated $event) {
            // Event will be automatically broadcast due to ShouldBroadcastNow interface
        });
    }
}
