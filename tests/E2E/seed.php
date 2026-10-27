<?php

use App\Models\Client;
use App\Models\EmploymentContract;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

// Run only against an explicitly selected disposable SQLite database.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'sqlite' || ! str_contains(config('database.connections.sqlite.database'), '/erp-qa-')) {
    throw new RuntimeException('E2E fixtures require APP_ENV=testing and a disposable /erp-qa- SQLite database.');
}

$employee = User::where('email', 'employee@thetables.com')->firstOrFail();
$employee->update([
    'job_title' => 'QA Engineer', 'work_arrangement' => 'remote',
    'birth_date' => '1995-02-03', 'appointment_date' => '2026-01-01',
    'residence' => 'Cairo QA Residence', 'national_id' => '29502031234567',
    'mobile' => '01012345678', 'other_phone' => '0223456789',
    'education' => 'Computer Science', 'salary' => 6000,
]);
$admin = User::factory()->create(['name' => 'QA Admin', 'email' => 'qa-admin@thetables.com', 'is_employee' => true, 'is_active' => true]);
$admin->assignRole('admin');
$other = User::factory()->create(['name' => 'Other QA Employee', 'email' => 'qa-other@thetables.com', 'is_employee' => true, 'is_active' => true]);
$other->assignRole('developer');
$client = Client::where('email', 'client@thetables.com')->firstOrFail();
$projects = [];
foreach (['QA Working Project', 'QA Restore Project', 'QA Permanent Project', 'QA Type Project'] as $title) {
    $project = Project::factory()->create(['title' => $title, 'status' => 'active', 'client_id' => $client->id, 'deadline' => now()->addDays(7)->toDateString()]);
    foreach ([$employee, $admin, User::where('email', 'manager@thetables.com')->firstOrFail()] as $member) {
        $project->members()->create(['user_id' => $member->id, 'role' => 'member']);
    }
    $projects[$title] = $project->id;
}
$task = Task::factory()->create(['title' => 'QA Timer Task', 'project_id' => $projects['QA Working Project'], 'assigned_to' => $employee->id, 'task_category' => 'testing']);
$contract = EmploymentContract::create([
    'employee_id' => $employee->id, 'employment_type' => 'full_time', 'base_salary' => 6000,
    'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'duration_months' => 12,
    'work_days' => ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday'],
    'work_hours_per_day' => 8, 'work_start_time' => '09:00', 'status' => 'active',
]);
echo json_encode(['employee' => $employee->id, 'admin' => $admin->id, 'other' => $other->id, 'task' => $task->id, 'contract' => $contract->id, 'projects' => $projects], JSON_PRETTY_PRINT).PHP_EOL;
