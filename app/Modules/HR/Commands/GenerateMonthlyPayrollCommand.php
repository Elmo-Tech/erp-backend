<?php

namespace App\Modules\HR\Commands;

use App\Modules\HR\Services\PayrollCalculatorService;
use App\Modules\Settings\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Artisan command: hr:generate-payroll
 *
 * Runs the PayrollCalculatorService for every active employee and persists
 * a Payslip record for the target month.
 *
 * Defaults to the PREVIOUS calendar month so it can be scheduled via
 * a monthly cron on the 1st of each month:
 *
 *   $schedule->command('hr:generate-payroll')->monthlyOn(1, '06:00');
 *
 * You can also target a specific period:
 *
 *   php artisan hr:generate-payroll --month=4 --year=2026
 *
 * Re-running is safe — only draft payslips are overwritten.
 */
class GenerateMonthlyPayrollCommand extends Command
{
    protected $signature = 'hr:generate-payroll
                            {--month= : Target month (1-12). Defaults to previous month.}
                            {--year=  : Target year. Defaults to the year of the previous month.}';

    protected $description = 'Generate monthly payslips for all active employees.';

    public function __construct(private readonly PayrollCalculatorService $calculator)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        // ── Resolve target period ─────────────────────────────────────────────
        $previousMonth = Carbon::now()->subMonth();

        $month = (int) ($this->option('month') ?: $previousMonth->month);
        $year  = (int) ($this->option('year')  ?: $previousMonth->year);

        if ($month < 1 || $month > 12) {
            $this->error('--month must be between 1 and 12.');
            return self::FAILURE;
        }

        $periodLabel = Carbon::create($year, $month, 1)->format('F Y');
        $this->info("🚀 Generating payroll for: {$periodLabel}");

        // ── Fetch active employees ────────────────────────────────────────────
        $employees = User::employees()
            ->where('is_active', true)
            ->with('employeeProfile')
            ->get();

        if ($employees->isEmpty()) {
            $this->warn('No active employees found. Nothing to generate.');
            return self::SUCCESS;
        }

        $this->info("   Found {$employees->count()} active employee(s).");

        // ── Process each employee ─────────────────────────────────────────────
        $generated = 0;
        $skipped   = 0;
        $failed    = 0;

        $bar = $this->output->createProgressBar($employees->count());
        $bar->start();

        foreach ($employees as $employee) {
            try {
                $payslip = $this->calculator->generateForUser($employee, $month, $year);

                if ($payslip->wasRecentlyCreated || $payslip->wasChanged()) {
                    $generated++;
                } else {
                    // Payslip exists and was not a draft — skipped intentionally.
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->newLine();
                $this->warn("   ⚠  Skipped user [{$employee->id}] {$employee->name}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // ── Summary ───────────────────────────────────────────────────────────
        $this->info("✅ Done for {$periodLabel}.");
        $this->table(
            ['Generated / Updated', 'Skipped (approved/paid)', 'Failed'],
            [[$generated, $skipped, $failed]]
        );

        return self::SUCCESS;
    }
}
