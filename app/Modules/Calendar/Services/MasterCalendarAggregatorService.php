<?php

namespace App\Modules\Calendar\Services;

use App\Modules\Calendar\Models\CalendarEvent;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Client\Models\Client;
use App\Modules\Finance\Models\FinanceInvoice;
use App\Modules\Finance\Models\Subscription;
use App\Modules\Lead\Models\Lead;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\PersonalTasks\Models\PersonalTimeEntry;
use App\Modules\Project\Models\Milestone;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Project\Models\ProjectTimeEntry;
use App\Modules\Settings\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

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
            ->merge(($user->can('view_attendance') || $user->can('manage_attendance')) ? $this->fetchAttendance($startDate, $endDate, $user) : collect())
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
            ->map(function (Meeting $meeting) {
                $subject = $meeting->model;
                $subjectMeta = $this->meetingSubjectMeta($subject);

                return [
                    'id'        => 'meeting-'.$meeting->id,
                    'type'      => 'meeting',
                    'title'     => $meeting->title,
                    'date'      => $meeting->scheduled_at->toDateString(),
                    'startTime' => $meeting->scheduled_at->toIso8601String(),
                    'endTime'   => $meeting->scheduled_at->copy()->addHour()->toIso8601String(),
                    'status'    => $meeting->status->value,
                    'meta'      => array_merge($subjectMeta, [
                        'description' => $meeting->description,
                        'notes' => $meeting->description,
                        'eventType'   => 'meeting',
                        'creatorId'   => $meeting->created_by,
                        'creatorName' => $meeting->createdBy?->name,
                        'meetingType' => $meeting->meeting_type,
                        'location' => $meeting->location?->value,
                        'locationDetails' => $meeting->location_details,
                        'internalAttendees' => $meeting->internal_attendees ?? [],
                        'externalAttendees' => $meeting->external_attendees ?? [],
                    ]),
                ];
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function meetingSubjectMeta(?object $subject): array
    {
        $meta = [
            'subjectType' => $subject ? get_class($subject) : null,
            'subjectId' => $subject?->id,
            'subjectName' => null,
            'clientId' => null,
            'clientName' => null,
            'projectId' => null,
            'projectTitle' => null,
            'leadId' => null,
            'leadTitle' => null,
        ];

        if ($subject instanceof Client) {
            return array_merge($meta, [
                'subjectName' => $subject->display_name ?? $subject->company_name,
                'clientId' => $subject->id,
                'clientName' => $subject->display_name ?? $subject->company_name,
            ]);
        }

        if ($subject instanceof Project) {
            $client = $subject->relationLoaded('client') ? $subject->client : $subject->client()->first();

            return array_merge($meta, [
                'subjectName' => $subject->title,
                'projectId' => $subject->id,
                'projectTitle' => $subject->title,
                'clientId' => $subject->client_id,
                'clientName' => $client?->display_name ?? $client?->company_name,
            ]);
        }

        if ($subject instanceof Lead) {
            return array_merge($meta, [
                'subjectName' => $subject->title,
                'leadId' => $subject->id,
                'leadTitle' => $subject->title,
            ]);
        }

        return $meta;
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

    private function fetchAttendance(Carbon $startDate, Carbon $endDate, User $user): Collection
    {
        $projectTimeEntriesByDate = ProjectTimeEntry::with(['task.project:id,title'])
            ->where('user_id', $user->id)
            ->whereBetween('start_time', [
                $startDate->copy()->startOfDay(),
                $endDate->copy()->endOfDay(),
            ])
            ->orderBy('start_time')
            ->get()
            ->groupBy(fn (ProjectTimeEntry $entry) => $entry->start_time?->toDateString());

        $personalTimeEntriesByDate = Schema::hasTable('personal_time_entries') && Schema::hasTable('personal_tasks')
            ? PersonalTimeEntry::with('personalTask:id,title,status')
                ->where('user_id', $user->id)
                ->whereBetween('start_time', [
                    $startDate->copy()->startOfDay(),
                    $endDate->copy()->endOfDay(),
                ])
                ->orderBy('start_time')
                ->get()
                ->groupBy(fn (PersonalTimeEntry $entry) => $entry->start_time?->toDateString())
            : collect();

        return Attendance::with('user:id,name,avatar')
            ->where('user_id', $user->id)
            ->whereBetween('date', [
                $startDate->copy()->toDateString(),
                $endDate->copy()->toDateString(),
            ])
            ->orderBy('date')
            ->get()
            ->map(function (Attendance $attendance) use ($projectTimeEntriesByDate, $personalTimeEntriesByDate) {
                $date = $attendance->date?->toDateString();
                $projectEntries = $projectTimeEntriesByDate->get($date, collect());
                $personalEntries = $personalTimeEntriesByDate->get($date, collect());

                $projectWorkedTasks = $projectEntries
                    ->groupBy('task_id')
                    ->map(function (Collection $taskEntries) {
                        /** @var ProjectTimeEntry $first */
                        $first = $taskEntries->first();
                        $durationMinutes = $taskEntries->sum(function (ProjectTimeEntry $entry): int {
                            if ($entry->duration_minutes !== null) {
                                return (int) $entry->duration_minutes;
                            }

                            if ($entry->start_time !== null && $entry->end_time !== null) {
                                return (int) $entry->start_time->diffInMinutes($entry->end_time);
                            }

                            return 0;
                        });

                        return [
                            'source' => 'project',
                            'taskId' => $first->task_id,
                            'projectId' => $first->task?->project_id,
                            'title' => $first->task?->title ?? 'Task #'.$first->task_id,
                            'status' => $first->task?->status?->value,
                            'projectTitle' => $first->task?->project?->title,
                            'durationMinutes' => $durationMinutes,
                            'entriesCount' => $taskEntries->count(),
                            'lastStartTime' => $taskEntries->max('start_time')?->toIso8601String(),
                        ];
                    })
                    ->values();

                $personalWorkedTasks = $personalEntries
                    ->groupBy('personal_task_id')
                    ->map(function (Collection $taskEntries) {
                        /** @var PersonalTimeEntry $first */
                        $first = $taskEntries->first();
                        $durationMinutes = $taskEntries->sum(function (PersonalTimeEntry $entry): int {
                            if ($entry->duration_minutes !== null) {
                                return (int) $entry->duration_minutes;
                            }

                            if ($entry->start_time !== null && $entry->end_time !== null) {
                                return (int) $entry->start_time->diffInMinutes($entry->end_time);
                            }

                            return 0;
                        });

                        return [
                            'source' => 'personal',
                            'taskId' => $first->personal_task_id,
                            'projectId' => null,
                            'title' => $first->personalTask?->title ?? 'Personal task #'.$first->personal_task_id,
                            'status' => $first->personalTask?->status,
                            'projectTitle' => 'Personal',
                            'durationMinutes' => $durationMinutes,
                            'entriesCount' => $taskEntries->count(),
                            'lastStartTime' => $taskEntries->max('start_time')?->toIso8601String(),
                        ];
                    })
                    ->values();

                return [
                    'id'        => 'attendance-'.$attendance->id,
                    'type'      => 'attendance',
                    'title'     => 'Attendance: '.ucfirst((string) $attendance->status),
                    'date'      => $attendance->date?->toDateString(),
                    'startTime' => $attendance->clock_in?->toIso8601String(),
                    'endTime'   => $attendance->clock_out?->toIso8601String(),
                    'status'    => $attendance->status,
                    'meta'      => [
                        'attendanceId' => $attendance->id,
                        'userId' => $attendance->user_id,
                        'userName' => $attendance->user?->name,
                        'clockIn' => $attendance->clock_in?->toIso8601String(),
                        'clockOut' => $attendance->clock_out?->toIso8601String(),
                        'isClockedIn' => $attendance->isClockedIn(),
                        'totalWorkedMinutes' => $attendance->total_worked_minutes,
                        'actualWorkedMinutes' => $attendance->getWorkedMinutes(),
                        'expectedWorkedMinutes' => $attendance->getExpectedWorkedMinutes(),
                        'stoppedMinutes' => $attendance->stopped_minutes,
                        'lateMinutes' => (int) $attendance->late_minutes,
                        'deductionMinutes' => (int) $attendance->deduction_minutes,
                        'idleMinutes' => $attendance->idle_minutes,
                        'usedGraceDay' => (bool) $attendance->used_grace_day,
                        'notes' => $attendance->notes,
                        'workedTasks' => $projectWorkedTasks->merge($personalWorkedTasks)->values(),
                    ],
                ];
            });
    }
}
