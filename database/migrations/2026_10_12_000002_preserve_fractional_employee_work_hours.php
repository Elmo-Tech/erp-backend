<?php

use App\Services\HR\SalaryRateCalculator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->decimal('work_hours_per_day', 4, 2)->default(8)->change();
        });

        DB::table('employment_contracts')->where('status', 'active')->orderBy('id')->chunkById(200, function ($contracts): void {
            foreach ($contracts as $contract) {
                DB::table('users')->where('id', $contract->employee_id)->update([
                    'salary' => $contract->base_salary,
                    'hourly_rate' => $contract->hourly_rate,
                    'work_hours_per_day' => $contract->work_hours_per_day,
                    'work_start_time' => $contract->work_start_time,
                    'internal_hourly_cost' => app(SalaryRateCalculator::class)->hourlyRate((float) $contract->base_salary, (float) $contract->work_hours_per_day),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedTinyInteger('work_hours_per_day')->default(8)->change();
        });
    }
};
