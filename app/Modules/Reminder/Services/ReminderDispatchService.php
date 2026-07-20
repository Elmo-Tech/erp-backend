<?php

namespace App\Modules\Reminder\Services;

use App\Modules\Client\Models\Client;
use App\Modules\Finance\Models\FinanceInvoice;
use App\Modules\Finance\Models\Subscription;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Notification\Notifications\ClientFollowUpReminderNotification;
use App\Modules\Notification\Notifications\FinanceInvoiceDueReminderNotification;
use App\Modules\Notification\Notifications\MeetingReminderNotification;
use App\Modules\Notification\Notifications\PersonalTaskDueReminderNotification;
use App\Modules\Notification\Notifications\ProjectTaskDueReminderNotification;
use App\Modules\Notification\Notifications\SubscriptionCollectionDueNotification;
use App\Modules\PersonalTasks\Models\PersonalTask;
use App\Modules\Project\Enums\ExecutionTaskStatus;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Reminder\Models\ReminderDispatch;
use App\Modules\Settings\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReminderDispatchService
{
    public function sendDueReminders(int $daysAhead = 2): array
    {
        return [
            'meetings' => $this->sendMeetingReminders(),
            'tasks' => $this->sendTaskDueReminders($daysAhead),
            'invoices' => $this->sendInvoiceDueReminders($daysAhead),
            'subscriptions' => $this->sendSubscriptionReminders($daysAhead),
            'client_followups' => $this->sendClientFollowUpReminders($daysAhead),
        ];
    }

    public function sendMeetingReminders(int $hoursAhead = 24): int
    {
        return Meeting::query()
            ->with('assignedTo:id,name,email')
            ->scheduled()
            ->whereBetween('scheduled_at', [now(), now()->addHours($hoursAhead)])
            ->get()
            ->sum(fn (Meeting $meeting): int => $this->notifyUsers(
                'meeting_upcoming',
                $meeting,
                $this->meetingRecipients($meeting),
                new MeetingReminderNotification($meeting),
                $meeting->scheduled_at?->toDateString()
            ));
    }

    public function sendTaskDueReminders(int $daysAhead = 2): int
    {
        $projectReminders = ProjectTask::query()
            ->with(['assignee:id,name,email', 'project:id,title'])
            ->whereNotNull('assignee_id')
            ->whereNotIn('status', [ExecutionTaskStatus::DONE->value])
            ->whereDate('due_date', '>=', now()->toDateString())
            ->whereDate('due_date', '<=', now()->addDays($daysAhead)->toDateString())
            ->get()
            ->sum(fn (ProjectTask $task): int => $this->notifyUsers(
                'project_task_due',
                $task,
                collect([$task->assignee])->filter(),
                new ProjectTaskDueReminderNotification($task),
                $task->due_date?->toDateString(),
                $this->dueReminderStage($task->due_date)
            ));

        $personalReminders = PersonalTask::query()
            ->with('user:id,name,email')
            ->where('status', '!=', 'done')
            ->whereDate('due_date', '>=', now()->toDateString())
            ->whereDate('due_date', '<=', now()->addDays($daysAhead)->toDateString())
            ->get()
            ->sum(fn (PersonalTask $task): int => $this->notifyUsers(
                'personal_task_due',
                $task,
                collect([$task->user])->filter(),
                new PersonalTaskDueReminderNotification($task),
                $task->due_date?->toDateString(),
                $this->dueReminderStage($task->due_date)
            ));

        return $projectReminders + $personalReminders;
    }

    public function sendInvoiceDueReminders(int $daysAhead = 3): int
    {
        $recipients = User::permission('view_finance')->get(['id', 'name', 'email']);

        return FinanceInvoice::query()
            ->with(['client:id,display_name,company_name,primary_email', 'project:id,title'])
            ->whereIn('status', ['sent', 'overdue'])
            ->whereBetween('due_date', [now()->toDateString(), now()->addDays($daysAhead)->toDateString()])
            ->get()
            ->sum(fn (FinanceInvoice $invoice): int => $this->notifyUsers(
                'finance_invoice_due',
                $invoice,
                $recipients,
                new FinanceInvoiceDueReminderNotification($invoice),
                $invoice->due_date?->toDateString()
            ));
    }

    public function sendSubscriptionReminders(int $daysAhead = 3): int
    {
        $recipients = User::permission('collect_subscription_payments')->get(['id', 'name', 'email']);

        return Subscription::query()
            ->with(['client:id,display_name,company_name,primary_email', 'project:id,title'])
            ->where('status', 'active')
            ->whereBetween('next_billing_date', [now()->toDateString(), now()->addDays($daysAhead)->toDateString()])
            ->get()
            ->sum(fn (Subscription $subscription): int => $this->notifyUsers(
                'subscription_collection_due',
                $subscription,
                $recipients,
                new SubscriptionCollectionDueNotification($subscription),
                $subscription->next_billing_date?->toDateString()
            ));
    }

    public function sendClientFollowUpReminders(int $daysAhead = 3): int
    {
        return Client::query()
            ->with('accountManager:id,name,email')
            ->whereNotNull('account_manager_id')
            ->whereNotNull('expected_close_date')
            ->whereIn('lifecycle_stage', ['lead', 'prospect', 'qualified', 'opportunity'])
            ->where('status', 'active')
            ->whereBetween('expected_close_date', [now()->toDateString(), now()->addDays($daysAhead)->toDateString()])
            ->get()
            ->sum(fn (Client $client): int => $this->notifyUsers(
                'client_followup_due',
                $client,
                collect([$client->accountManager])->filter(),
                new ClientFollowUpReminderNotification($client),
                $client->expected_close_date?->toDateString()
            ));
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    private function notifyUsers(
        string $type,
        Model $remindable,
        Collection $recipients,
        Notification $notification,
        ?string $scheduledFor,
        ?string $dedupeScope = null
    ): int {
        return $recipients
            ->unique('id')
            ->sum(function (User $recipient) use ($type, $remindable, $notification, $scheduledFor, $dedupeScope): int {
                $key = $this->reminderKey($type, $remindable, $recipient, $scheduledFor, $dedupeScope);

                return DB::transaction(function () use ($key, $type, $remindable, $recipient, $notification, $scheduledFor): int {
                    if (ReminderDispatch::query()->where('reminder_key', $key)->exists()) {
                        return 0;
                    }

                    $recipient->notify($notification);

                    ReminderDispatch::query()->create([
                        'reminder_key' => $key,
                        'reminder_type' => $type,
                        'remindable_type' => $remindable::class,
                        'remindable_id' => $remindable->getKey(),
                        'notifiable_type' => $recipient::class,
                        'notifiable_id' => $recipient->getKey(),
                        'channel' => 'mail',
                        'scheduled_for' => $scheduledFor,
                        'sent_at' => now(),
                    ]);

                    return 1;
                });
            });
    }

    /**
     * @return Collection<int, User>
     */
    private function meetingRecipients(Meeting $meeting): Collection
    {
        $ids = collect($meeting->internal_attendees ?? [])
            ->push($meeting->assigned_to)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        return User::query()
            ->whereIn('id', $ids)
            ->get(['id', 'name', 'email']);
    }

    private function reminderKey(string $type, Model $remindable, User $recipient, ?string $scheduledFor, ?string $dedupeScope = null): string
    {
        return implode(':', [
            $type,
            str_replace('\\', '.', $remindable::class),
            $remindable->getKey(),
            str_replace('\\', '.', $recipient::class),
            $recipient->getKey(),
            $scheduledFor ?? 'once',
            $dedupeScope ?? 'default',
        ]);
    }

    private function dueReminderStage(mixed $dueDate): string
    {
        $daysUntilDue = now()->startOfDay()->diffInDays(Carbon::parse($dueDate)->startOfDay(), false);

        if ($daysUntilDue === 0) {
            return 'due_today';
        }

        return 'due_in_'.$daysUntilDue.'_days';
    }
}
