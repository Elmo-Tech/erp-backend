<?php

namespace Database\Seeders;

use App\Models\Lead;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        
        // Create test lead
        $lead = Lead::create([
            'name' => 'Test Lead',
            'email' => 'test@example.com',
            'company' => 'Test Company',
            'source' => 'website',
            'status' => 'new'
        ]);

        // Create test quotation
        $quotation = Quotation::create([
            'lead_id' => $lead->id,
            'estimations' => ['frontend_hours' => 40, 'backend_hours' => 60],
            'hourly_rate' => 50.00,
            'discount_percentage' => 10.0,
            'final_price' => 4500.00,
            'currency' => 'USD',
            'status' => 'approved'
        ]);

        // Create test project
        $project = Project::first();
        if (!$project) {
            $project = Project::create([
                'title' => 'Test Project',
                'description' => 'Test project for dashboard',
                'status' => 'active',
                'client_id' => $user->id,
                'budget' => 5000.00,
                'deadline' => now()->addDays(30)
            ]);
        }

        // Create test task
        $task = Task::create([
            'project_id' => $project->id,
            'title' => 'Test Task',
            'description' => 'Test task for dashboard',
            'status' => 'todo',
            'assignee_id' => $user->id,
            'estimated_hours' => 8
        ]);

        // Create test time entry
        TimeEntry::create([
            'user_id' => $user->id,
            'trackable_id' => $project->id,
            'trackable_type' => Project::class,
            'start_time' => now()->subHours(2),
            'end_time' => now(),
            'duration' => 120
        ]);

        $this->command->info('Test data created successfully!');
    }
}