<?php

namespace App\Console\Commands;

use App\Models\PointTransaction;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DeletePointsByDateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'points:delete-by-date 
                            {date : The date to delete points from (YYYY-MM-DD format)}
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--user= : Delete points for specific user ID only}
                            {--type= : Delete specific transaction type only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete all point transactions from a specific date and recalculate user totals';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $date = $this->argument('date');
        $isDryRun = $this->option('dry-run');
        $userId = $this->option('user');
        $type = $this->option('type');

        // Validate date format
        try {
            $targetDate = Carbon::createFromFormat('Y-m-d', $date);
        } catch (\Exception $e) {
            $this->error('Invalid date format. Please use YYYY-MM-DD format.');
            return 1;
        }

        $this->info("🗓️  Target Date: {$targetDate->format('Y-m-d')}");
        
        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No actual deletions will be performed');
        }

        // Build query for PointTransaction table
        $pointTransactionQuery = PointTransaction::whereDate('created_at', $targetDate);

        // Apply user filter if specified
        if ($userId) {
            $user = User::find($userId);
            if (!$user) {
                $this->error("User with ID {$userId} not found.");
                return 1;
            }
            
            $pointTransactionQuery->where('user_id', $userId);
            $this->info("👤 Filtering for user: {$user->name} (ID: {$userId})");
        }

        // Apply type filter if specified
        if ($type) {
            $pointTransactionQuery->where('type', $type);
            $this->info("🏷️  Filtering for type: {$type}");
        }

        // Get records to be deleted
        $pointTransactions = $pointTransactionQuery->with('user:id,name,total_points')->get();

        // Show summary
        $this->info("\n📊 SUMMARY:");
        $this->info("Point Transactions to delete: {$pointTransactions->count()}");

        if ($pointTransactions->isEmpty()) {
            $this->warn('No records found for the specified criteria.');
            return 0;
        }

        // Show affected users
        $affectedUsers = collect()
            ->merge($pointTransactions->pluck('user'))
            ->unique('id')
            ->sortBy('name');

        $this->info("\n👥 AFFECTED USERS ({$affectedUsers->count()}):");
        foreach ($affectedUsers as $user) {
            $userPtCount = $pointTransactions->where('user_id', $user->id)->count();
            $totalPointsToDelete = $pointTransactions->where('user_id', $user->id)->sum('points');
            
            $this->line("  • {$user->name} (ID: {$user->id}) - Current: {$user->total_points} points");
            $this->line("    - PointTransactions: {$userPtCount}");
            $this->line("    - Points to remove: {$totalPointsToDelete}");
        }

        // Show detailed records if not too many
        if ($pointTransactions->count() <= 20) {
            $this->info("\n📋 DETAILED RECORDS:");
            
            if ($pointTransactions->isNotEmpty()) {
                $this->info("Point Transactions:");
                foreach ($pointTransactions as $pt) {
                    $this->line("  • ID: {$pt->id} | User: {$pt->user->name} | Points: {$pt->points} | Type: {$pt->type->value} | Reason: {$pt->reason}");
                }
            }
        }

        // Confirm deletion
        if (!$isDryRun) {
            if (!$this->confirm("\n⚠️  Are you sure you want to delete these records? This action cannot be undone!")) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        if ($isDryRun) {
            $this->info("\n✅ Dry run completed. No records were deleted.");
            return 0;
        }

        // Perform deletion in transaction
        DB::transaction(function () use ($pointTransactionQuery, $affectedUsers) {
            $this->info("\n🗑️  Deleting records...");
            
            // Delete records
            $deletedPt = $pointTransactionQuery->delete();
            
            $this->info("Deleted {$deletedPt} Point Transactions");
            
            // Recalculate user totals
            $this->info("\n🔄 Recalculating user point totals...");
            
            foreach ($affectedUsers as $user) {
                $user->refresh();
                
                // Calculate total from remaining PointTransactions
                $totalFromPt = PointTransaction::where('user_id', $user->id)->sum('points');
                
                $newTotal = $totalFromPt;
                $oldTotal = $user->total_points;
                
                $user->update(['total_points' => $newTotal]);
                
                $this->line("  • {$user->name}: {$oldTotal} → {$newTotal} points");
            }
        });

        $this->info("\n✅ Operation completed successfully!");
        $this->info("All point transactions from {$targetDate->format('Y-m-d')} have been deleted and user totals recalculated.");
        
        return 0;
    }
}
