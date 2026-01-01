<?php

namespace App\Providers;

use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Observers\ProjectObserver;
use App\Observers\TaskObserver;
use App\Observers\TimeEntryObserver;
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
    }
}
