<?php

namespace App\Providers;

use App\Events\NotificationCreated;
use App\Events\TimerUpdated;
use App\Jobs\SendWebPush;
use App\Models\CodeReview;
use App\Models\InnovationLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDeliverable;
use App\Models\TaskTestScenario;
use App\Models\TimeEntry;
use App\Models\User;
use App\Observers\CodeReviewObserver;
use App\Observers\InnovationLogObserver;
use App\Observers\ProjectObserver;
use App\Observers\TaskDeliverableObserver;
use App\Observers\TaskObserver;
use App\Observers\TaskTestScenarioObserver;
use App\Observers\TimeEntryObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        RateLimiter::for('api', function (Request $request): Limit {
            $identity = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(max(1, (int) config('rate_limits.api_per_minute', 300)))->by((string) $identity);
        });

        RateLimiter::for('login', function (Request $request): Limit {
            $identity = Str::lower(trim((string) $request->input('email')));

            return Limit::perMinute(max(1, (int) config('rate_limits.failed_logins_per_minute', 10)))
                ->by($identity.'|'.$request->ip())
                ->after(fn ($response): bool => in_array($response->getStatusCode(), [401, 422], true));
        });

        // Access control terminals push their events to a public endpoint.
        // The limit is generous but still protects the endpoint from abuse.
        RateLimiter::for('device-webhook', function (Request $request): Limit {
            return Limit::perMinute((int) config('devices.webhook.rate_limit_per_minute', 120))
                ->by((string) $request->route('token'));
        });

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
        Event::listen(NotificationSent::class, function ($event): void {
            if ($event->channel !== 'database' || ! $event->notifiable instanceof User) {
                return;
            }
            $data = $event->response?->data ?? [];
            $data['tag'] = 'notification-'.$event->notification->id;
            $data['url'] ??= isset($data['project_id']) ? '/dashboard/projects/'.$data['project_id'] : '/dashboard';
            SendWebPush::dispatch($event->notifiable->id, $data)->afterCommit();
        });
        Event::listen(NotificationCreated::class, function (NotificationCreated $event) {
            // Event will be automatically broadcast due to ShouldBroadcastNow interface
        });

        Event::listen(TimerUpdated::class, function (TimerUpdated $event) {
            // Event will be automatically broadcast due to ShouldBroadcastNow interface
        });
    }
}
