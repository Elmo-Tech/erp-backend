<?php

namespace App\Console\Commands;

use App\Modules\User\Models\User;
use App\Services\Reporting\EmployeeReviewService;
use Database\Seeders\TestDataSeeder;
use Illuminate\Console\Command;

class TestMonitoringDataCommand extends Command
{
    protected $signature = 'test:monitoring-data';

    protected $description = 'Test monitoring data generation and API response';

    public function handle()
    {
        $this->info('🚀 Testing Monitoring Data Generation...');

        // Clear existing test data first
        $this->info('🧹 Clearing existing test data...');
        \DB::table('time_entries')->where('trackable_type', 'App\\Models\\Task')->delete();
        \DB::table('task_assignees')->delete();
        \DB::table('tasks')->delete();
        \DB::table('project_members')->delete();
        \DB::table('projects')->where('title', 'like', '%Test%')->orWhere('title', 'like', '%E-commerce%')->orWhere('title', 'like', '%Mobile%')->delete();
        \DB::table('quotations')->delete();
        \DB::table('leads')->delete();

        // Run test data seeder
        $this->info('📊 Creating enhanced test data...');
        $seeder = new TestDataSeeder;
        $seeder->run();

        // Get the first user (admin)
        $user = User::first();
        if (! $user) {
            $this->error('❌ No users found in database');

            return 1;
        }

        $this->info("👤 Testing with user: {$user->name} (ID: {$user->id})");

        // Test the employee review service
        $this->info('🔍 Testing EmployeeReviewService...');
        $service = app(EmployeeReviewService::class);

        try {
            $reviewData = $service->getEmployeeReview($user->id, []);

            $this->info('✅ Employee review data generated successfully!');
            $this->line('');

            // Display summary
            $this->info('📈 Data Summary:');
            $this->line("Projects: {$reviewData['projects']['total']} total, {$reviewData['projects']['active']} active, {$reviewData['projects']['completed']} completed");
            $this->line('Projects list count: '.count($reviewData['projects']['projects']));
            $this->line('Weekly time tracking entries: '.count($reviewData['trends']['weeklyTimeTracking']));
            $this->line('Weekly task completion entries: '.count($reviewData['trends']['weeklyTaskCompletion']));

            // Display projects details
            if (! empty($reviewData['projects']['projects'])) {
                $this->line('');
                $this->info('📋 Projects Details:');
                foreach ($reviewData['projects']['projects'] as $project) {
                    $status = is_object($project['status']) ? $project['status']->value : $project['status'];
                    $this->line("- {$project['title']} ({$status}) - {$project['userTimeSpent']}h");
                }
            }

            // Display project breakdown
            if (! empty($reviewData['projectBreakdown'])) {
                $this->line('');
                $this->info('🏗️ Project Breakdown (for PDF):');
                foreach ($reviewData['projectBreakdown'] as $project) {
                    $this->line("- {$project['name']}: {$project['totalHours']}h - {$project['keyDeliverables']}");
                }
            } else {
                $this->warn('⚠️ Project breakdown is empty - this might be the issue!');
            }

            // Display trends details
            if (! empty($reviewData['trends']['weeklyTimeTracking'])) {
                $this->line('');
                $this->info('📊 Weekly Time Tracking:');
                foreach ($reviewData['trends']['weeklyTimeTracking'] as $week) {
                    $this->line("- Week {$week['week']}: {$week['totalHours']}h ({$week['entries']} entries)");
                }
            }

            if (! empty($reviewData['trends']['weeklyTaskCompletion'])) {
                $this->line('');
                $this->info('✅ Weekly Task Completion:');
                foreach ($reviewData['trends']['weeklyTaskCompletion'] as $week) {
                    $this->line("- Week {$week['week']}: {$week['completed']} completed, {$week['onTime']} on time");
                }
            }

            // Test database queries directly
            $this->line('');
            $this->info('🔍 Direct Database Queries:');

            $projectCount = \DB::table('projects')->count();
            $this->line("Total projects in DB: {$projectCount}");

            $memberCount = \DB::table('project_members')->where('user_id', $user->id)->count();
            $this->line("User is member of {$memberCount} projects");

            $taskCount = \DB::table('tasks')->count();
            $this->line("Total tasks in DB: {$taskCount}");

            $assigneeCount = \DB::table('task_assignees')->where('user_id', $user->id)->count();
            $this->line("User is assigned to {$assigneeCount} tasks");

            $timeEntryCount = \DB::table('time_entries')->where('user_id', $user->id)->count();
            $this->line("User has {$timeEntryCount} time entries");

        } catch (\Exception $e) {
            $this->error('❌ Error generating employee review data:');
            $this->error($e->getMessage());
            $this->line('');
            $this->error('Stack trace:');
            $this->error($e->getTraceAsString());

            return 1;
        }

        $this->info('');
        $this->info('🎉 Test completed successfully!');
        $this->info('💡 You can now test the frontend with user ID: '.$user->id);
        $this->info('📝 Check Laravel logs for detailed debugging info: tail -f storage/logs/laravel.log');

        return 0;
    }
}
