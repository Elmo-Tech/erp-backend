<?php

namespace App\Console\Commands;

use App\Models\EvaluationPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CreateEvaluationPeriodCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'performance:create-period {--force : Force creation even if period exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new bi-monthly evaluation period automatically';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = Carbon::now();

        // Determine the current bi-monthly period
        // Periods: Jan-Feb, Mar-Apr, May-Jun, Jul-Aug, Sep-Oct, Nov-Dec
        $month = $now->month;
        $year = $now->year;

        // Calculate start month (odd months: 1, 3, 5, 7, 9, 11)
        $startMonth = $month % 2 === 0 ? $month - 1 : $month;
        $endMonth = $startMonth + 1;

        $startDate = Carbon::create($year, $startMonth, 1)->startOfMonth();
        $endDate = Carbon::create($year, $endMonth, 1)->endOfMonth();

        // Generate period name
        $startMonthName = $startDate->format('F');
        $endMonthName = $endDate->format('F');
        $periodName = "{$startMonthName}-{$endMonthName} {$year}";

        // Check if period already exists
        $existingPeriod = EvaluationPeriod::where('start_date', $startDate->toDateString())
            ->where('end_date', $endDate->toDateString())
            ->first();

        if ($existingPeriod && ! $this->option('force')) {
            $this->info("Evaluation period '{$periodName}' already exists (ID: {$existingPeriod->id})");

            return Command::SUCCESS;
        }

        if ($existingPeriod && $this->option('force')) {
            $this->warn('Period exists but --force flag used. Skipping creation.');

            return Command::SUCCESS;
        }

        // Create the new period
        $period = EvaluationPeriod::create([
            'name' => $periodName,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'draft',
            'created_by' => null, // System created
        ]);

        $this->info("Created evaluation period: {$periodName} (ID: {$period->id})");
        $this->info("Start Date: {$startDate->toDateString()}");
        $this->info("End Date: {$endDate->toDateString()}");
        $this->info('Status: draft');
        $this->newLine();
        $this->info('To activate this period and create evaluations for all employees, run:');
        $this->info("  POST /api/v1/performance/periods/{$period->id}/activate");

        return Command::SUCCESS;
    }
}
