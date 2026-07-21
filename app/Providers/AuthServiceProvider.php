<?php

namespace App\Providers;

use App\Modules\Calendar\Models\CalendarEvent;
use App\Modules\Client\Models\Client;
use App\Modules\Finance\Models\FinanceInvoice;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Project\Models\ProjectTimeEntry;
use App\Modules\Settings\Models\User;
use App\Policies\CalendarEventPolicy;
use App\Policies\ClientPolicy;
use App\Policies\FinanceInvoicePolicy;
use App\Policies\MeetingPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\ProjectTimeEntryPolicy;
use App\Policies\TaskPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        CalendarEvent::class => CalendarEventPolicy::class,
        Client::class => ClientPolicy::class,
        FinanceInvoice::class => FinanceInvoicePolicy::class,
        Meeting::class => MeetingPolicy::class,
        Project::class => ProjectPolicy::class,
        ProjectTask::class => TaskPolicy::class,
        ProjectTimeEntry::class => ProjectTimeEntryPolicy::class,
    ];

    public function boot(): void
    {
        Sanctum::authenticateAccessTokensUsing(
            fn (PersonalAccessToken $accessToken, bool $isValid): bool => $isValid
                && $accessToken->tokenable instanceof User
                && $accessToken->tokenable->is_active
        );
    }
}
