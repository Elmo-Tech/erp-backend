<?php

namespace App\Console\Commands;

use App\Enums\PointTransactionType;
use App\Models\PointTransaction;
use App\Models\User;
use App\Models\UserPoint;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateUserPointsToPointTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'points:migrate-user-points
                            {--dry-run : Show what would be migrated without writing}
                            {--user= : Migrate a specific user ID only}
                            {--date-from= : Start date (YYYY-MM-DD)}
                            {--date-to= : End date (YYYY-MM-DD)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate UserPoint records into PointTransaction as the single source of truth';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $userId = $this->option('user');
        $dateFrom = $this->option('date-from');
        $dateTo = $this->option('date-to');

        $query = UserPoint::query();

        if ($userId) {
            $user = User::find($userId);
            if (! $user) {
                $this->error("User with ID {$userId} not found.");

                return 1;
            }
            $query->where('user_id', $userId);
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $userPoints = $query->orderBy('created_at')->get();

        if ($userPoints->isEmpty()) {
            $this->warn('No UserPoint records found for the given filters.');

            return 0;
        }

        $this->info('Found UserPoint records: '.$userPoints->count());
        if ($isDryRun) {
            $this->warn('DRY RUN: No records will be written.');
        }

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($userPoints, $isDryRun, &$created, &$skipped) {
            foreach ($userPoints as $up) {
                $transactionType = $up->transaction_type instanceof PointTransactionType
                    ? $up->transaction_type
                    : PointTransactionType::tryFrom($up->transaction_type);

                $transactionType = $transactionType ?? ($up->points_change >= 0
                    ? PointTransactionType::MANUAL_BONUS
                    : PointTransactionType::MANUAL_DEDUCTION);

                $exists = PointTransaction::where('user_id', $up->user_id)
                    ->where('points', $up->points_change)
                    ->where('type', $transactionType)
                    ->where('reason', $up->reason)
                    ->where('created_at', $up->created_at)
                    ->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }

                if (! $isDryRun) {
                    PointTransaction::create([
                        'user_id' => $up->user_id,
                        'points' => $up->points_change,
                        'type' => $transactionType,
                        'source_type' => null,
                        'source_id' => null,
                        'reason' => $up->reason,
                        'balance_after' => $up->points_after,
                        'created_by' => $up->created_by,
                        'updated_by' => $up->updated_by,
                        'created_at' => $up->created_at,
                        'updated_at' => $up->updated_at,
                    ]);
                }

                $created++;
            }
        });

        $this->info("Created: {$created}");
        $this->info("Skipped (already exists): {$skipped}");

        if ($isDryRun) {
            $this->warn('Dry run completed. No records were written.');
        }

        return 0;
    }
}
