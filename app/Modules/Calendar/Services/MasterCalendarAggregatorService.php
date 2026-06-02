<?php

namespace App\Modules\Calendar\Services;

use App\Modules\Calendar\Models\CalendarEvent;
use App\Modules\Finance\Models\FinanceInvoice;
use App\Modules\Finance\Models\Subscription;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Project\Models\Milestone;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Settings\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Aggregates data from multiple modules into a unified calendar feed.
 *
 * Each item in the returned collection has a consistent shape:
 *
 *   [
 *     'id'         => string  — prefixed unique ID, e.g. "event-12", "task-7", "invoice-3"
 *     'type'       => string  — "event" | "task" | "invoice" | "milestone"
 *     'title'      => string
 *     'date'       => string  — ISO-8601 date (YYYY-MM-DD) used for calendar placement
 *     'startTime'  => string|null  — ISO-8601 datetime (events only)
 *     'endTime'    => string|null  — ISO-8601 datetime (events only)
 *     'status'     => string|null
 *     'meta'       => array   — type-specific extra fields
 *   ]
 */
class MasterCalendarAggregatorService
{
    /**
     * Aggregate all calendar items within the given date range.
     *
     * @param  Carbon  $startDate  Inclusive start of the range
     * @param  Carbon  $endDate    Inclusive end of the range
     * @return Collection<int, array<string, mixed>>
     */
    public function aggregate(Carbon $startDate, Carbon $endDate, User $user): Collection
    {
        return collect()
            ->merge($user->can('view_calendar_events') ? $this->fetchEvents($startDate, $endDate) : collect())
            ->merge($user->can('view_calendar_meetings') ? $this->fetchMeetings($startDate, $endDate) : collect())
            ->merge($user->can('view_calendar_tasks') ? $this->fetchProjectTasks($startDate, $endDate) : collect())
            ->merge($user->can('view_calendar_invoices') ? $this->fetchInvoices($startDate, $endDate) : collect())
            ->merge($user->can('view_calendar_milestones') ? $this->fetchMilestones($startDate, $endDate) : collect())
            ->merge($user->can('view_calendar_subscriptions') ? $this->fetchSubscriptions($startDate, $endDate) : collect())
            ->sortBy('date')
            ->values();
    }

    /**
     * Fetch polymorphic Meeting records whose scheduled_at falls within the range.
     */
    private function fetchMeetings(Carbon $startDate, Carbon $endDate): Collection
    {
        return Meeting::with(['assignedTo:id,name', 'createdBy:id,name', 'model'])
            ->whereBetween('scheduled_at', [
                $startDate->copy()->startOfDay(),
                $endDate->copy()->endOfDay(),
            ])
            ->get()
            ->map(fn (Meeting $meeting) => [
                'id'        => 'meeting-'.$meeting->id,
                'type'      => 'meeting',
                'title'     => $meeting->title,
                'date'      => $meeting->scheduled_at->toDateString(),
                'startTime' => $meeting->scheduled_at->toIso8601String(),
                'endTime'   => $meeting->scheduled_at->copy()->addHour()->toIso8601String(),
                'status'    => $meeting->status->value,
                'meta'      => [
                    'description' => $meeting->description,
                    'eventType'   => 'meeting',
                    'creatorId'   => $meeting->created_by,
                    'creatorName' => $meeting->createdBy?->name,
                    'meetingType' => $meeting->meeting_type,
                    'internalAttendees' => $meeting->internal_attendees ?? [],
                    'externalAttendees' => $meeting->external_attendees ?? [],
                ],
            ]);
    }

    // -------------------------------------------------------------------------
    // Private fetchers
    // -------------------------------------------------------------------------

    /**
     * Fetch CalendarEvent records whose start_time falls within the range.
     */
    private function fetchEvents(Carbon $startDate, Carbon $endDate): Collection
    {
        return CalendarEvent::with('creator:id,name')
            ->whereBetween('start_time', [
                $startDate->startOfDay(),
                $endDate->endOfDay(),
            ])
            ->get()
            ->map(fn (CalendarEvent $event) => [
                'id'        => 'event-'.$event->id,
                'type'      => 'event',
                'title'     => $event->title,
                'date'      => $event->start_time->toDateString(),
                'startTime' => $event->start_time->toIso8601String(),
                'endTime'   => $event->end_time->toIso8601String(),
                'status'    => $event->event_type,
                'meta'      => [
                    'description' => $event->description,
                    'eventType'   => $event->event_type,
                    'creatorId'   => $event->user_id,
                    'creatorName' => $event->creator?->name,
                ],
            ]);
    }

    /**
     * Fetch ProjectTask records whose due_date falls within the range.
     */
    private function fetchProjectTasks(Carbon $startDate, Carbon $endDate): Collection
    {
        return ProjectTask::with(['project:id,title', 'assignee:id,name'])
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->get()
            ->map(fn (ProjectTask $task) => [
                'id'        => 'task-'.$task->id,
                'type'      => 'task',
                'title'     => $task->title,
                'date'      => $task->due_date->toDateString(),
                'startTime' => null,
                'endTime'   => null,
                'status'    => $task->status->value,
                'meta'      => [
                    'projectId'    => $task->project_id,
                    'projectTitle' => $task->project?->title,
                    'assigneeId'   => $task->assignee_id,
                    'assigneeName' => $task->assignee?->name,
                    'milestoneId'  => $task->milestone_id,
                ],
            ]);
    }

    /**
     * Fetch FinanceInvoice records whose due_date falls within the range.
     */
    private function fetchInvoices(Carbon $startDate, Carbon $endDate): Collection
    {
        return FinanceInvoice::with(['client:id,display_name,company_name', 'project:id,title'])
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->get()
            ->map(fn (FinanceInvoice $invoice) => [
                'id'        => 'invoice-'.$invoice->id,
                'type'      => 'invoice',
                'title'     => 'Invoice '.$invoice->invoice_number,
                'date'      => $invoice->due_date->toDateString(),
                'startTime' => null,
                'endTime'   => null,
                'status'    => $invoice->status,
                'meta'      => [
                    'invoiceNumber' => $invoice->invoice_number,
                    'clientId'      => $invoice->client_id,
                    'clientName'    => $invoice->client?->display_name ?? $invoice->client?->company_name,
                    'projectId'     => $invoice->project_id,
                    'projectTitle'  => $invoice->project?->title,
                    'totalAmount'   => (float) $invoice->total_amount,
                    'currency'      => 'USD',
                ],
            ]);
    }

    /**
     * Fetch Milestone records whose due_date falls within the range.
     */
    private function fetchMilestones(Carbon $startDate, Carbon $endDate): Collection
    {
        return Milestone::with('project:id,title')
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->get()
            ->map(fn (Milestone $milestone) => [
                'id'        => 'milestone-'.$milestone->id,
                'type'      => 'milestone',
                'title'     => $milestone->title,
                'date'      => $milestone->due_date->toDateString(),
                'startTime' => null,
                'endTime'   => null,
                'status'    => $milestone->status?->value,
                'meta'      => [
                    'milestoneId'        => $milestone->id,
                    'projectId'          => $milestone->project_id,
                    'projectTitle'       => $milestone->project?->title,
                    'progressPercentage' => $milestone->progress_percentage,
                    'invoiceId'          => $milestone->invoice_id,
                    'paymentStageId'     => $milestone->payment_stage_id,
                ],
            ]);
    }

    private function fetchSubscriptions(Carbon $startDate, Carbon $endDate): Collection
    {
        return Subscription::with(['client:id,display_name,company_name', 'project:id,title'])
            ->where('status', 'active')
            ->whereNotNull('next_billing_date')
            ->whereBetween('next_billing_date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->get()
            ->map(fn (Subscription $subscription) => [
                'id'        => 'subscription-'.$subscription->id,
                'type'      => 'subscription',
                'title'     => $subscription->name,
                'date'      => $subscription->next_billing_date->toDateString(),
                'startTime' => null,
                'endTime'   => null,
                'status'    => $subscription->status,
                'meta'      => [
                    'subscriptionId' => $subscription->id,
                    'billingCycle' => $subscription->billing_cycle,
                    'clientId' => $subscription->client_id,
                    'clientName' => $subscription->client?->display_name ?? $subscription->client?->company_name,
                    'projectId' => $subscription->project_id,
                    'projectTitle' => $subscription->project?->title,
                    'totalAmount' => (float) $subscription->cost,
                    'currency' => $subscription->currency,
                ],
            ]);
    }
}
