<?php

use App\Services\HR\SalaryRateCalculator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $calculator = app(SalaryRateCalculator::class);

        DB::table('users')
            ->whereNotNull('salary')
            ->where('work_hours_per_day', '>', 0)
            ->orderBy('id')
            ->chunkById(200, function ($users) use ($calculator): void {
                foreach ($users as $user) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update([
                            'internal_hourly_cost' => $calculator->hourlyRate(
                                (float) $user->salary,
                                (float) $user->work_hours_per_day
                            ),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Previous manually entered rates cannot be reconstructed safely.
    }
};
