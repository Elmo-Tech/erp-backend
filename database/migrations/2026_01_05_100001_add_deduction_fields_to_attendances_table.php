<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Additional attendance fields for deduction tracking:
     * - deduction_minutes: Minutes to deduct from salary (after grace period)
     * - used_grace_day: Whether this day used a grace day
     * - expected_clock_in: The expected clock-in time for this user
     * - expected_clock_out: Calculated expected clock-out time
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Deduction tracking
            $table->unsignedInteger('deduction_minutes')->default(0)->after('idle_minutes');
            $table->boolean('used_grace_day')->default(false)->after('deduction_minutes');
            
            // Expected times (stored for historical accuracy)
            $table->time('expected_clock_in')->nullable()->after('used_grace_day');
            $table->time('expected_clock_out')->nullable()->after('expected_clock_in');
            
            // Notes for manual adjustments
            $table->text('notes')->nullable()->after('expected_clock_out');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn([
                'deduction_minutes',
                'used_grace_day',
                'expected_clock_in',
                'expected_clock_out',
                'notes',
            ]);
        });
    }
};
