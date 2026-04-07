<?php

namespace App\Console\Commands;

use App\Services\HR\AttendanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ResetGraceDaysCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:reset-grace-days 
                            {--force : Force reset even if not a new month}
                            {--dry-run : Show what would be reset without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset grace days and salary deductions for all employees (runs automatically monthly)';

    /**
     * Execute the console command.
     */
    public function handle(AttendanceService $attendanceService): int
    {
        $isDryRun = $this->option('dry-run');
        $isForced = $this->option('force');

        $this->info('🔄 Starting monthly reset process (grace days + salary deductions)...');

        if ($isDryRun) {
            $this->warn('🧪 DRY RUN MODE - No changes will be made');
        }

        // Check if it's appropriate to reset (unless forced)
        if (! $isForced && ! $this->shouldReset()) {
            $this->error('❌ Monthly reset is not due. Use --force to override.');

            return Command::FAILURE;
        }

        if ($isDryRun) {
            // Show what would be reset
            $employees = \App\Models\User::employees()
                ->where(function ($query) {
                    $query->where('grace_days_used', '>', 0)
                        ->orWhere('total_deductions', '>', 0);
                })
                ->get(['id', 'name', 'email', 'grace_days_used', 'grace_days_allowed', 'total_deductions']);

            $this->table(
                ['ID', 'Name', 'Email', 'Grace Used', 'Grace Allowed', 'Deductions'],
                $employees->map(fn ($user) => [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->grace_days_used,
                    $user->grace_days_allowed,
                    '$'.number_format($user->total_deductions, 2),
                ])
            );

            $totalEmployees = \App\Models\User::employees()->count();
            $this->info("📊 Would reset grace days and deductions for {$totalEmployees} employees");
            $this->info("📊 {$employees->count()} employees currently have used grace days or deductions");

            return Command::SUCCESS;
        }

        // Perform the actual reset
        $resetCount = $attendanceService->resetAllGraceDays();

        // Log the operation
        Log::info('Monthly reset completed', [
            'employees_reset' => $resetCount,
            'reset_date' => now()->toDateString(),
            'triggered_by' => 'artisan_command',
            'forced' => $isForced,
            'items_reset' => ['grace_days_used', 'total_deductions'],
        ]);

        $this->info("✅ Successfully reset grace days and salary deductions for {$resetCount} employees");

        return Command::SUCCESS;
    }

    /**
     * Check if monthly reset should run (first day of month)
     */
    private function shouldReset(): bool
    {
        return now()->day === 1;
    }
}
