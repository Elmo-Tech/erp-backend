<?php

namespace App\Console\Commands;

use App\Models\InnovationLog;
use App\Models\User;
use Illuminate\Console\Command;

class CreateTestInnovationLogCommand extends Command
{
    protected $signature = 'create:test-innovation-log';

    protected $description = 'Create test innovation log data';

    public function handle()
    {
        $this->info('🚀 Creating test innovation log...');

        $user = User::first();
        if (! $user) {
            $this->error('No users found. Please create a user first.');

            return;
        }

        // Create innovation log
        $innovationLog = InnovationLog::create([
            'user_id' => $user->id,
            'tool_name' => 'AI Code Review Assistant',
            'category' => 'process_improvement',
            'description' => 'Developed an AI assistant that automatically reviews code changes and suggests improvements, reducing review time by 40%.',
            'impact_description' => 'Reduced code review time from 2 hours to 1.2 hours on average per PR.',
            'impact_level' => 'high',
            'time_saved_minutes' => 48, // 0.8 hours * 60 minutes
            'is_verified' => false,
        ]);

        $this->info("✅ Created innovation log: {$innovationLog->tool_name}");
        $this->info('📧 This should trigger notifications to admins');

        // Check notifications
        $notificationCount = \DB::table('notifications')->count();
        $this->info("📊 Total notifications in database: {$notificationCount}");

        return 0;
    }
}
