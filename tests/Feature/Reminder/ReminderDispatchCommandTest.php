<?php

namespace Tests\Feature\Reminder;

use App\Modules\Client\Models\Client;
use App\Modules\Finance\Models\FinanceInvoice;
use App\Modules\Finance\Models\Subscription;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Notification\Notifications\ClientFollowUpReminderNotification;
use App\Modules\Notification\Notifications\FinanceInvoiceDueReminderNotification;
use App\Modules\Notification\Notifications\MeetingReminderNotification;
use App\Modules\Notification\Notifications\ProjectTaskDueReminderNotification;
use App\Modules\Notification\Notifications\SubscriptionCollectionDueNotification;
use App\Modules\Project\Enums\ExecutionTaskStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Reminder\Models\ReminderDispatch;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReminderDispatchCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_reminder_command_sends_email_notifications_once_per_recipient(): void
    {
        Notification::fake();

        $assignee = User::factory()->create();
        $financeUser = User::factory()->create();

        Permission::findOrCreate('view_finance', 'web');
        Permission::findOrCreate('collect_subscription_payments', 'web');
        $financeUser->givePermissionTo(['view_finance', 'collect_subscription_payments']);

        $client = Client::factory()->create(['account_manager_id' => $assignee->id]);
        $prospect = Client::factory()->create([
            'account_manager_id' => $assignee->id,
            'lifecycle_stage' => 'prospect',
            'expected_close_date' => now()->addDay()->toDateString(),
        ]);
        $project = Project::factory()->create(['client_id' => $client->id]);

        Meeting::query()->create([
            'model_type' => Client::class,
            'model_id' => $client->id,
            'status' => 'scheduled',
            'title' => 'Kickoff follow-up',
            'assigned_to' => $assignee->id,
            'internal_attendees' => [$assignee->id],
            'scheduled_at' => now()->addHours(3),
        ]);

        ProjectTask::query()->create([
            'project_id' => $project->id,
            'title' => 'Prepare deployment checklist',
            'status' => ExecutionTaskStatus::TO_DO,
            'assignee_id' => $assignee->id,
            'due_date' => now()->addDay()->toDateString(),
        ]);

        FinanceInvoice::query()->create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'invoice_number' => 'FINV-2026-000001',
            'issue_date' => now()->subDays(7)->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'subtotal' => 1000,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 1000,
            'status' => 'sent',
        ]);

        Subscription::query()->create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'name' => 'Hosting',
            'provider' => 'Cloud Provider',
            'billing_cycle' => 'monthly',
            'cost' => 50,
            'currency' => 'USD',
            'next_billing_date' => now()->addDay()->toDateString(),
            'status' => 'active',
        ]);

        $this->artisan('reminders:send-due')
            ->assertExitCode(0);

        Notification::assertSentTo($assignee, MeetingReminderNotification::class);
        Notification::assertSentTo($assignee, ProjectTaskDueReminderNotification::class);
        Notification::assertSentTo($assignee, ClientFollowUpReminderNotification::class);
        Notification::assertSentTo($financeUser, FinanceInvoiceDueReminderNotification::class);
        Notification::assertSentTo($financeUser, SubscriptionCollectionDueNotification::class);
        $this->assertSame(5, ReminderDispatch::query()->count());

        $this->artisan('reminders:send-due')
            ->assertExitCode(0);

        Notification::assertSentToTimes($assignee, MeetingReminderNotification::class, 1);
        Notification::assertSentToTimes($assignee, ProjectTaskDueReminderNotification::class, 1);
        Notification::assertSentToTimes($assignee, ClientFollowUpReminderNotification::class, 1);
        Notification::assertSentToTimes($financeUser, FinanceInvoiceDueReminderNotification::class, 1);
        Notification::assertSentToTimes($financeUser, SubscriptionCollectionDueNotification::class, 1);
        $this->assertSame(5, ReminderDispatch::query()->count());
    }
}
