<?php

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Enums\TaskCategory;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Modules\CRM\Models\Client;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Project\Enums\ProjectStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMember;
use App\Modules\SOP\Models\DailyCheckin;
use App\Modules\SOP\Models\Directive;
use App\Modules\Task\Models\Task;
use App\Modules\Task\Models\TaskAssignee;
use App\Modules\Task\Models\TaskDependency;
use App\Modules\TimeEntry\Models\TimeEntry;
use App\Modules\User\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class DashboardDemoSeeder extends Seeder
{
    /**
     * Seed realistic demo records for the permission-driven dashboard widgets.
     */
    public function run(): void
    {
        $founder = User::where('email', 'founder@elmo.tech')->firstOrFail();
        $pmo = User::where('email', 'pmo@elmo.tech')->firstOrFail();
        $techLead = User::where('email', 'techlead@elmo.tech')->firstOrFail();
        $developer = User::where('email', 'dev@elmo.tech')->firstOrFail();

        $directive = $this->seedDirective($founder);

        $clients = $this->seedClients();
        $projects = $this->seedProjects($clients, $founder);
        $tasks = $this->seedTasks($projects, $developer, $techLead, $founder);

        $this->seedProjectMembers($projects, [$pmo, $techLead, $developer]);
        $this->seedTaskAssignees($tasks, $developer, $techLead, $founder);
        $this->seedTaskDependencies($tasks, $pmo);
        $this->seedDailyCheckins($directive, $pmo);
        $this->seedTimeEntries($tasks, $developer, $techLead);
        $this->seedInvoices($projects, $founder);
    }

    private function seedDirective(User $creator): Directive
    {
        return Directive::updateOrCreate(
            ['title' => 'Morning Operating Rhythm'],
            [
                'description' => 'Review the focus rules, acknowledge the day plan, and commit the top priorities before entering the dashboard.',
                'phases' => [
                    [
                        'name' => 'Silent Hours',
                        'description' => 'Deep work only. No meetings or non-critical Slack until the first delivery checkpoint.',
                        'starts_at' => '09:30',
                        'ends_at' => '11:30',
                        'minutes' => 120,
                        'items' => ['Close async threads', 'Keep one active work item', 'Escalate only production blockers'],
                    ],
                    [
                        'name' => 'Delivery Checkpoint',
                        'description' => 'Move visible work forward and update project status before handoff.',
                        'starts_at' => '13:00',
                        'ends_at' => '13:20',
                        'minutes' => 20,
                        'items' => ['Update task status', 'Post blockers', 'Confirm next milestone'],
                    ],
                    [
                        'name' => 'Manager Signal',
                        'description' => 'Surface risks early so PMO and Tech Lead can unblock the team.',
                        'starts_at' => '16:30',
                        'ends_at' => '17:00',
                        'minutes' => 30,
                        'items' => ['Call out dependency risk', 'Confirm review queue', 'Log final time entries'],
                    ],
                ],
                'target_permissions' => ['view_morning_sop'],
                'is_recurring' => true,
                'created_by' => $creator->id,
            ]
        );
    }

    /**
     * @return array<int, Client>
     */
    private function seedClients(): array
    {
        return [
            Client::updateOrCreate(
                ['primary_email' => 'ops@northstar-demo.test'],
                [
                    'type' => 'company',
                    'display_name' => 'Northstar Operations',
                    'company_name' => 'Northstar Logistics',
                    'primary_phone' => '+1 415 555 0131',
                    'lifecycle_stage' => 'client',
                    'status' => 'active',
                    'country' => 'United States',
                    'notes' => 'Demo logistics account for operational dashboard data.',
                ]
            ),
            Client::updateOrCreate(
                ['primary_email' => 'product@atlas-demo.test'],
                [
                    'type' => 'company',
                    'display_name' => 'Atlas Product Team',
                    'company_name' => 'Atlas Retail',
                    'primary_phone' => '+1 646 555 0198',
                    'lifecycle_stage' => 'client',
                    'status' => 'active',
                    'country' => 'United States',
                    'notes' => 'Demo retail account for PMO timeline work.',
                ]
            ),
        ];
    }

    /**
     * @param array<int, Client> $clients
     * @return array<string, Project>
     */
    private function seedProjects(array $clients, User $creator): array
    {
        $projectRows = [
            'northstar-erp' => [
                'client_id' => $clients[0]->id,
                'title' => 'Northstar ERP Rollout',
                'description' => 'Operational ERP rollout with finance, delivery, and executive dashboards.',
                'status' => ProjectStatus::DEVELOPMENT,
                'budget' => 85000,
                'deadline' => now()->addDays(18)->toDateString(),
            ],
            'atlas-portal' => [
                'client_id' => $clients[1]->id,
                'title' => 'Atlas Client Portal',
                'description' => 'Self-service portal for invoices, project visibility, and milestone sign-off.',
                'status' => ProjectStatus::ACTIVE,
                'budget' => 62000,
                'deadline' => now()->addDays(32)->toDateString(),
            ],
            'matrix-dashboard' => [
                'client_id' => $clients[0]->id,
                'title' => 'Matrix Dashboard System',
                'description' => 'Permission-driven internal dashboard for Founder, PMO, Tech Lead, and Developer workflows.',
                'status' => ProjectStatus::TESTING,
                'budget' => 48000,
                'deadline' => now()->addDays(9)->toDateString(),
            ],
            'quality-automation' => [
                'client_id' => $clients[1]->id,
                'title' => 'Quality Automation Layer',
                'description' => 'Automated quality gates, test reporting, and release confidence metrics.',
                'status' => ProjectStatus::UI_UX,
                'budget' => 36000,
                'deadline' => now()->addDays(44)->toDateString(),
            ],
        ];

        $projects = [];

        Project::unguarded(function () use ($projectRows, $creator, &$projects): void {
            foreach ($projectRows as $key => $row) {
                if (Schema::hasColumn('projects', 'old_user_client_id')) {
                    $row['old_user_client_id'] = $creator->id;
                }

                $projects[$key] = Project::withoutGlobalScopes()->updateOrCreate(
                    ['title' => $row['title']],
                    [
                        ...$row,
                        'created_by' => $creator->id,
                        'updated_by' => $creator->id,
                    ]
                );
            }
        });

        return $projects;
    }

    /**
     * @param array<string, Project> $projects
     * @return array<string, Task>
     */
    private function seedTasks(array $projects, User $developer, User $techLead, User $creator): array
    {
        $taskRows = [
            'api-contract' => ['project' => 'northstar-erp', 'assigned_to' => $developer->id, 'title' => 'Finalize dashboard API contract', 'status' => TaskStatus::IN_PROGRESS, 'priority' => TaskPriority::HIGH, 'start_date' => now()->subDays(5), 'due_date' => now()->addDay(), 'estimated_hours' => 8, 'total_time_spent' => 210],
            'seed-data' => ['project' => 'northstar-erp', 'assigned_to' => $developer->id, 'title' => 'Seed matrix demo dashboard data', 'status' => TaskStatus::TODO, 'priority' => TaskPriority::HIGH, 'start_date' => now()->subDays(2), 'due_date' => now()->addDays(2), 'estimated_hours' => 6, 'total_time_spent' => 90],
            'review-flow' => ['project' => 'atlas-portal', 'assigned_to' => $techLead->id, 'title' => 'Review client portal auth flow', 'status' => TaskStatus::REVIEW, 'priority' => TaskPriority::MEDIUM, 'start_date' => now()->subDays(8), 'due_date' => now()->addDays(4), 'estimated_hours' => 5, 'total_time_spent' => 180],
            'timeline-ui' => ['project' => 'matrix-dashboard', 'assigned_to' => $developer->id, 'title' => 'Polish Gantt-lite timeline widget', 'status' => TaskStatus::TESTING, 'priority' => TaskPriority::MEDIUM, 'start_date' => now()->subDays(10), 'due_date' => now()->addDays(3), 'estimated_hours' => 10, 'total_time_spent' => 420],
            'blocked-export' => ['project' => 'matrix-dashboard', 'assigned_to' => $developer->id, 'title' => 'Connect blocker export to PMO dashboard', 'status' => TaskStatus::IN_PROGRESS, 'priority' => TaskPriority::URGENT, 'start_date' => now()->subDays(3), 'due_date' => now()->subDay(), 'estimated_hours' => 4, 'total_time_spent' => 60],
            'qa-gate' => ['project' => 'quality-automation', 'assigned_to' => $techLead->id, 'title' => 'Define automated release quality gate', 'status' => TaskStatus::COMPLETED, 'priority' => TaskPriority::MEDIUM, 'start_date' => now()->subDays(14), 'due_date' => now()->subDays(2), 'estimated_hours' => 7, 'total_time_spent' => 390, 'completed_at' => now()->subDays(2)],
        ];

        $tasks = [];

        foreach ($taskRows as $key => $row) {
            $project = $projects[$row['project']];

            $tasks[$key] = Task::updateOrCreate(
                ['title' => $row['title']],
                [
                    'project_id' => $project->id,
                    'assigned_to' => $row['assigned_to'],
                    'description' => 'Demo task for the permission-driven matrix dashboard.',
                    'status' => $row['status'],
                    'type' => TaskType::SUBTASK,
                    'priority' => $row['priority'],
                    'task_category' => TaskCategory::FRONTEND,
                    'start_date' => Carbon::parse($row['start_date'])->toDateString(),
                    'due_date' => Carbon::parse($row['due_date'])->toDateString(),
                    'estimated_hours' => $row['estimated_hours'],
                    'total_time_spent' => $row['total_time_spent'],
                    'completed_at' => isset($row['completed_at']) ? Carbon::parse($row['completed_at']) : null,
                    'created_by' => $creator->id,
                    'updated_by' => $creator->id,
                ]
            );
        }

        return $tasks;
    }

    /**
     * @param array<string, Project> $projects
     * @param array<int, User> $users
     */
    private function seedProjectMembers(array $projects, array $users): void
    {
        foreach ($projects as $project) {
            foreach ($users as $user) {
                ProjectMember::updateOrCreate(
                    ['project_id' => $project->id, 'user_id' => $user->id],
                    ['role' => $user->hasRole('tech_lead') ? 'lead' : 'member']
                );
            }
        }
    }

    /**
     * @param array<string, Task> $tasks
     */
    private function seedTaskAssignees(array $tasks, User $developer, User $techLead, User $creator): void
    {
        foreach ($tasks as $task) {
            TaskAssignee::updateOrCreate(
                ['task_id' => $task->id, 'user_id' => $task->assigned_to],
                [
                    'role' => $task->assigned_to === $techLead->id ? 'lead' : 'developer',
                    'is_primary' => true,
                    'points_earned' => $task->status === TaskStatus::COMPLETED ? 180 : 60,
                    'created_by' => $creator->id,
                    'updated_by' => $creator->id,
                ]
            );
        }

        TaskAssignee::updateOrCreate(
            ['task_id' => $tasks['blocked-export']->id, 'user_id' => $techLead->id],
            [
                'role' => 'reviewer',
                'is_primary' => false,
                'points_earned' => 0,
                'created_by' => $creator->id,
                'updated_by' => $creator->id,
            ]
        );
    }

    /**
     * @param array<string, Task> $tasks
     */
    private function seedTaskDependencies(array $tasks, User $pmo): void
    {
        TaskDependency::updateOrCreate(
            [
                'task_id' => $tasks['blocked-export']->id,
                'depends_on_task_id' => $tasks['review-flow']->id,
            ],
            [
                'dependency_type' => TaskDependency::TYPE_FINISH_TO_START,
                'lag_days' => 0,
                'is_escalated' => true,
                'escalated_by' => $pmo->id,
                'escalated_at' => now()->subHours(2),
                'resolved_at' => null,
            ]
        );
    }

    private function seedDailyCheckins(Directive $directive, User $pmo): void
    {
        DailyCheckin::updateOrCreate(
            [
                'user_id' => $pmo->id,
                'directive_id' => $directive->id,
            ],
            [
                'submitted_at' => now()->startOfDay()->addHours(9)->addMinutes(15),
                'focus_items' => [
                    'Clear the escalated export dependency with the Tech Lead.',
                    'Refresh delivery risk notes before the founder review.',
                    'Confirm milestone dates for the Northstar rollout.',
                ],
            ]
        );
    }

    /**
     * @param array<string, Task> $tasks
     */
    private function seedTimeEntries(array $tasks, User $developer, User $techLead): void
    {
        $entries = [
            [$developer, $tasks['api-contract'], now()->subDays(4)->setTime(10, 0), 150],
            [$developer, $tasks['timeline-ui'], now()->subDays(2)->setTime(13, 0), 210],
            [$developer, $tasks['blocked-export'], now()->setTime(9, 30), 90],
            [$techLead, $tasks['review-flow'], now()->subDays(3)->setTime(11, 0), 180],
            [$techLead, $tasks['qa-gate'], now()->subDays(7)->setTime(14, 0), 240],
        ];

        foreach ($entries as [$user, $task, $start, $duration]) {
            TimeEntry::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'trackable_id' => $task->id,
                    'trackable_type' => Task::class,
                    'start_time' => $start,
                ],
                [
                    'end_time' => $start->copy()->addMinutes($duration),
                    'duration_minutes' => $duration,
                ]
            );
        }
    }

    /**
     * @param array<string, Project> $projects
     */
    private function seedInvoices(array $projects, User $creator): void
    {
        $rows = [
            ['project' => 'northstar-erp', 'amount' => 28000, 'status' => InvoiceStatus::PAID, 'due_date' => now()->subDays(15), 'paid_at' => now()->subDays(8)],
            ['project' => 'atlas-portal', 'amount' => 18500, 'status' => InvoiceStatus::SENT, 'due_date' => now()->addDays(12), 'paid_at' => null],
            ['project' => 'matrix-dashboard', 'amount' => 22000, 'status' => InvoiceStatus::OVERDUE, 'due_date' => now()->subDays(6), 'paid_at' => null],
            ['project' => 'quality-automation', 'amount' => 12000, 'status' => InvoiceStatus::PAID, 'due_date' => now()->subDays(5), 'paid_at' => now()->subDays(2)],
        ];

        foreach ($rows as $index => $row) {
            Invoice::updateOrCreate(
                ['invoice_number' => sprintf('INV-%d-DEMO-%02d', now()->year, $index + 1)],
                [
                    'project_id' => $projects[$row['project']]->id,
                    'amount' => $row['amount'],
                    'currency' => 'USD',
                    'status' => $row['status'],
                    'due_date' => Carbon::parse($row['due_date'])->toDateString(),
                    'paid_at' => $row['paid_at'] ? Carbon::parse($row['paid_at']) : null,
                    'notes' => 'Demo invoice for founder financial dashboard.',
                    'created_by' => $creator->id,
                    'updated_by' => $creator->id,
                ]
            );
        }
    }
}
