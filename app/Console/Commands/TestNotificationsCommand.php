<?php

namespace App\Console\Commands;

use App\Models\InnovationLog;
use App\Modules\Task\Models\Task;
use App\Modules\User\Models\User;
use App\Modules\Notification\Notifications\InnovationLogSubmitted;
use App\Modules\Notification\Notifications\InnovationLogVerified;
use App\Modules\Notification\Notifications\TaskAssigned;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class TestNotificationsCommand extends Command
{
    protected $signature = 'test:notifications';

    protected $description = 'Test notification system functionality';

    public function handle()
    {
        $this->info('🔔 Testing Notification System...');

        // Get first user
        $user = User::first();
        if (! $user) {
            $this->error('No users found. Please create a user first.');

            return;
        }

        $this->info("Testing with user: {$user->name} ({$user->email})");

        // Test 1: Innovation Log Submitted (to admins)
        $this->info('📝 Test 1: Innovation Log Submitted Notification');

        $innovationLog = InnovationLog::first();
        if ($innovationLog) {
            $admins = User::role(['admin', 'super-admin'])->get();
            if ($admins->isNotEmpty()) {
                Notification::send($admins, new InnovationLogSubmitted($innovationLog));
                $this->info("✅ Sent InnovationLogSubmitted to {$admins->count()} admin(s)");
            } else {
                $this->warn('⚠️  No admins found to test InnovationLogSubmitted');
            }
        } else {
            $this->warn('⚠️  No innovation logs found to test');
        }

        // Test 2: Innovation Log Verified (to user)
        $this->info('📝 Test 2: Innovation Log Verified Notification');
        if ($innovationLog) {
            $innovationLog->user->notify(new InnovationLogVerified($innovationLog));
            $this->info("✅ Sent InnovationLogVerified to {$innovationLog->user->name}");
        }

        // Test 3: Task Assigned
        $this->info('📋 Test 3: Task Assigned Notification');
        $task = Task::with('assignees.user')->first();
        if ($task && $task->assignees->isNotEmpty()) {
            foreach ($task->assignees as $taskAssignee) {
                $taskAssignee->user->notify(new TaskAssigned($task, 'Test System'));
            }
            $this->info("✅ Sent TaskAssigned to {$task->assignees->count()} assignee(s)");
        } else {
            $this->warn('⚠️  No tasks with assignees found to test');
        }

        // Check notification count
        $totalNotifications = \DB::table('notifications')->count();
        $this->info("📊 Total notifications in database: {$totalNotifications}");

        // Check queue jobs
        $queueJobs = \DB::table('jobs')->count();
        $this->info("⏳ Pending queue jobs: {$queueJobs}");

        if ($queueJobs > 0) {
            $this->warn('⚠️  You have pending queue jobs. Run: php artisan queue:work');
        }

        $this->info('🎉 Notification test completed!');
        $this->info('💡 Check the notifications table and run queue:work to process jobs');
    }
}
