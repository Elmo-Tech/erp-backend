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

        // Create multiple test projects
        $projects = [];
        
        // Project 1 - Active project
        $project1 = Project::create([
            'title' => 'E-commerce Website',
            'description' => 'Full-stack e-commerce platform',
            'status' => 'active',
            'client_id' => $user->id,
            'budget' => 5000.00,
            'deadline' => now()->addDays(30)
        ]);

        // Add user as project member
        $project1->members()->create([
            'user_id' => $user->id,
            'role' => 'lead'
        ]);

        // Create tasks for project 1
        $task1 = Task::create([
            'project_id' => $project1->id,
            'title' => 'Frontend Development',
            'description' => 'Build React frontend',
            'status' => 'completed',
            'estimated_hours' => 40,
            'completed_at' => now()->subDays(1)
        ]);

        $task2 = Task::create([
            'project_id' => $project1->id,
            'title' => 'Backend API',
            'description' => 'Build Laravel API',
            'status' => 'in_progress',
            'estimated_hours' => 30
        ]);

        // Add user as task assignee
        $task1->assignees()->create([
            'user_id' => $user->id,
            'role' => 'developer',
            'is_primary' => true
        ]);

        $task2->assignees()->create([
            'user_id' => $user->id,
            'role' => 'developer',
            'is_primary' => true
        ]);

        // Create time entries for tasks
        TimeEntry::create([
            'user_id' => $user->id,
            'trackable_id' => $task1->id,
            'trackable_type' => 'App\\Models\\Task',
            'start_time' => now()->subDays(2)->setHour(9),
            'end_time' => now()->subDays(2)->setHour(17),
            'duration_minutes' => 480 // 8 hours
        ]);

        TimeEntry::create([
            'user_id' => $user->id,
            'trackable_id' => $task2->id,
            'trackable_type' => 'App\\Models\\Task',
            'start_time' => now()->subDays(1)->setHour(10),
            'end_time' => now()->subDays(1)->setHour(16),
            'duration_minutes' => 360 // 6 hours
        ]);

        // Project 2 - Completed project
        $project2 = Project::create([
            'title' => 'Mobile App',
            'description' => 'React Native mobile application',
            'status' => 'completed',
            'client_id' => $user->id,
            'budget' => 3000.00,
            'deadline' => now()->subDays(10)
        ]);

        // Add user as project member
        $project2->members()->create([
            'user_id' => $user->id,
            'role' => 'developer'
        ]);

        // Create completed task for project 2
        $task3 = Task::create([
            'project_id' => $project2->id,
            'title' => 'Mobile UI Development',
            'description' => 'Build mobile interface',
            'status' => 'completed',
            'estimated_hours' => 25,
            'completed_at' => now()->subDays(5)
        ]);

        $task3->assignees()->create([
            'user_id' => $user->id,
            'role' => 'developer',
            'is_primary' => true
        ]);

        // Create time entry for completed task
        TimeEntry::create([
            'user_id' => $user->id,
            'trackable_id' => $task3->id,
            'trackable_type' => 'App\\Models\\Task',
            'start_time' => now()->subDays(7)->setHour(9),
            'end_time' => now()->subDays(7)->setHour(14),
            'duration_minutes' => 300 // 5 hours
        ]);

        TimeEntry::create([
            'user_id' => $user->id,
            'trackable_id' => $task3->id,
            'trackable_type' => 'App\\Models\\Task',
            'start_time' => now()->subDays(6)->setHour(9),
            'end_time' => now()->subDays(6)->setHour(13),
            'duration_minutes' => 240 // 4 hours
        ]);

        if ($this->command) {
            $this->command->info('Enhanced test data created successfully!');
            $this->command->info('Projects created: 2');
            $this->command->info('Tasks created: 3');
            $this->command->info('Time entries created: 4');
            $this->command->info('Total hours logged: ' . (480 + 360 + 300 + 240) / 60 . ' hours');
        }
    }
}