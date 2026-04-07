<?php

namespace App\Console\Commands;

use App\Models\PointPeriod;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClosePointPeriodCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'points:close-period 
                            {--force : Force close even if period end date not reached}
                            {--dry-run : Show what would happen without actually closing}
                            {--notes= : Optional notes about this period closure}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Close current point period, archive results, reset user points, and create new period';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $isForced = $this->option('force');
        $notes = $this->option('notes');

        $this->info('🔄 Starting Point Period Closure Process...');

        if ($isDryRun) {
            $this->warn('🧪 DRY RUN MODE - No changes will be made');
        }

        // Get active period
        $activePeriod = PointPeriod::getActivePeriod();

        if (! $activePeriod) {
            $this->error('❌ No active period found!');

            return Command::FAILURE;
        }

        $this->info("📅 Active Period: {$activePeriod->name}");
        $this->info("   Start: {$activePeriod->start_date->format('Y-m-d')}");
        $this->info("   End: {$activePeriod->end_date->format('Y-m-d')}");

        // Check if period should be closed
        if (! $isForced && now()->lt($activePeriod->end_date)) {
            $daysRemaining = now()->diffInDays($activePeriod->end_date);
            $this->error("❌ Period end date not reached yet ({$daysRemaining} days remaining).");
            $this->info('💡 Use --force to close anyway.');

            return Command::FAILURE;
        }

        // Get period statistics
        $stats = $this->getPeriodStatistics($activePeriod);

        $this->info("\n📊 PERIOD STATISTICS:");
        $this->info("   Total Transactions: {$stats['totalTransactions']}");
        $this->info("   Total Points Awarded: {$stats['totalPointsAwarded']}");
        $this->info("   Total Points Deducted: {$stats['totalPointsDeducted']}");
        $this->info("   Net Points: {$stats['netPoints']}");
        $this->info("   Employees with Points: {$stats['employeesWithPoints']}");

        // Show leaderboard
        $leaderboard = $activePeriod->getLeaderboard(10);

        if (! empty($leaderboard)) {
            $this->info("\n🏆 TOP 10 LEADERBOARD:");
            $this->table(
                ['Rank', 'Name', 'Email', 'Points'],
                collect($leaderboard)->map(fn ($item, $index) => [
                    $index + 1,
                    $item['name'],
                    $item['email'],
                    $item['points'],
                ])
            );
        }

        // Show employees who will be reset
        $employeesWithPoints = User::employees()
            ->where('total_points', '!=', 0)
            ->get(['id', 'name', 'email', 'total_points']);

        if ($employeesWithPoints->isNotEmpty()) {
            $this->info("\n👥 EMPLOYEES TO RESET ({$employeesWithPoints->count()}):");
            $this->table(
                ['ID', 'Name', 'Current Points', 'After Reset'],
                $employeesWithPoints->map(fn ($user) => [
                    $user->id,
                    $user->name,
                    $user->total_points,
                    0,
                ])
            );
        }

        if ($isDryRun) {
            $this->info("\n✅ Dry run completed. No changes made.");

            return Command::SUCCESS;
        }

        // Confirm before proceeding
        if (! $this->confirm("\n⚠️  This will close the period and reset all employee points. Continue?", false)) {
            $this->warn('Operation cancelled.');

            return Command::SUCCESS;
        }

        // Perform the closure
        DB::transaction(function () use ($activePeriod, $notes) {
            // 1. Close the active period
            $activePeriod->update([
                'is_active' => false,
                'closed_at' => now(),
                'closed_by' => auth()->id() ?? 1, // System user if run via cron
                'notes' => $notes,
            ]);

            $this->info("\n✅ Period closed: {$activePeriod->name}");

            // 2. Reset all employee points to 0
            $resetCount = User::employees()->update(['total_points' => 0]);

            $this->info("✅ Reset points for {$resetCount} employees");

            // 3. Create new period (next 2 months)
            $newStartDate = $activePeriod->end_date->copy()->addDay()->startOfDay();
            $newEndDate = $newStartDate->copy()->addMonths(2)->subDay()->endOfDay();

            $newPeriod = PointPeriod::create([
                'name' => $newStartDate->format('M').'-'.$newEndDate->format('M Y'),
                'start_date' => $newStartDate,
                'end_date' => $newEndDate,
                'is_active' => true,
            ]);

            $this->info("✅ New period created: {$newPeriod->name}");
            $this->info("   Start: {$newPeriod->start_date->format('Y-m-d')}");
            $this->info("   End: {$newPeriod->end_date->format('Y-m-d')}");
        });

        // Log the operation
        Log::info('Point period closed', [
            'period_id' => $activePeriod->id,
            'period_name' => $activePeriod->name,
            'employees_reset' => $employeesWithPoints->count(),
            'closed_at' => now()->toDateTimeString(),
            'closed_by' => auth()->id() ?? 'system',
            'notes' => $notes,
        ]);

        $this->info("\n🎉 Point period closure completed successfully!");
        $this->info('💡 Historical data is preserved in point_transactions table.');

        return Command::SUCCESS;
    }

    /**
     * Get statistics for the period
     */
    private function getPeriodStatistics(PointPeriod $period): array
    {
        $transactions = $period->transactions;

        return [
            'totalTransactions' => $transactions->count(),
            'totalPointsAwarded' => $transactions->where('points', '>', 0)->sum('points'),
            'totalPointsDeducted' => abs($transactions->where('points', '<', 0)->sum('points')),
            'netPoints' => $transactions->sum('points'),
            'employeesWithPoints' => $transactions->pluck('user_id')->unique()->count(),
        ];
    }
}
