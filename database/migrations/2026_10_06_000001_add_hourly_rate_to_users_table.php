<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Client decision 1: manual hourly rate for overtime (payable rate),
            // separate from computed internal_hourly_cost. Null = fallback to
            // salary / (dailyRateDivisor * work_hours_per_day).
            $table->decimal('hourly_rate', 10, 4)->nullable()->after('salary');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('hourly_rate');
        });
    }
};
