<?php

namespace App\Providers;

use App\Events\NotificationCreated;
use App\Events\TimerUpdated;
use App\Models\InnovationLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Observers\InnovationLogObserver;
use App\Observers\ProjectObserver;
use App\Observers\TaskObserver;
use App\Observers\TimeEntryObserver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

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
        TimeEntry::observe(TimeEntryObserver::class);
        InnovationLog::observe(InnovationLogObserver::class);

        // Register event listeners for broadcasting
        Event::listen(NotificationCreated::class, function (NotificationCreated $event) {
            // Event will be automatically broadcast due to ShouldBroadcastNow interface
        });

        Event::listen(TimerUpdated::class, function (TimerUpdated $event) {
            // Event will be automatically broadcast due to ShouldBroadcastNow interface
        });
    }
}
