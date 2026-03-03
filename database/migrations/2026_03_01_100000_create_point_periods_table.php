<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Point Periods: Track bi-monthly point cycles for bonus calculations
     * - Each period is 2 months
     * - At period close, user points are archived and reset to 0
     * - Historical data is preserved for reporting
     */
    public function up(): void
    {
        Schema::create('point_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Jan-Feb 2026", "Mar-Apr 2026"
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('is_active');
            $table->index(['start_date', 'end_date']);
        });
        
        // Add period_id to point_transactions
        Schema::table('point_transactions', function (Blueprint $table) {
            $table->foreignId('period_id')->nullable()->after('user_id')->constrained('point_periods')->nullOnDelete();
            $table->index('period_id');
        });
        
        // Create initial active period (current month + next month)
        $now = now();
        $startDate = $now->copy()->startOfMonth();
        $endDate = $now->copy()->addMonth()->endOfMonth();
        
        DB::table('point_periods')->insert([
            'name' => $startDate->format('M') . '-' . $endDate->format('M Y'),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('point_transactions', function (Blueprint $table) {
            $table->dropForeign(['period_id']);
            $table->dropIndex(['period_id']);
            $table->dropColumn('period_id');
        });
        
        Schema::dropIfExists('point_periods');
    }
};
