<?php

namespace App\Modules\Calendar\Services;

use App\Modules\Calendar\Models\CalendarEvent;
use App\Modules\Finance\Models\FinanceInvoice;
use App\Modules\Project\Models\ProjectTask;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Aggregates data from multiple modules into a unified calendar feed.
 *
 * Each item in the returned collection has a consistent shape:
 *
 *   [
 *     'id'         => string  — prefixed unique ID, e.g. "event-12", "task-7", "invoice-3"
 *     'type'       => string  — "event" | "task" | "invoice"
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
    public function aggregate(Carbon $startDate, Carbon $endDate): Collection
    {
        return collect()
            ->merge($this->fetchEvents($startDate, $endDate))
            ->merge($this->fetchProjectTasks($startDate, $endDate))
            ->merge($this->fetchInvoices($startDate, $endDate))
            ->sortBy('date')
            ->values();
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
        return FinanceInvoice::with('client:id,name')
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
                    'clientName'    => $invoice->client?->name,
                    'totalAmount'   => (float) $invoice->total_amount,
                    'currency'      => 'USD',
                ],
            ]);
    }
}
