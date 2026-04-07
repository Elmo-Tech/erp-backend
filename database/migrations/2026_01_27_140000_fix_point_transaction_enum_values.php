<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fix invalid enum values in point_transactions table
        DB::table('point_transactions')
            ->where('type', 'task_completed')
            ->update(['type' => 'task_on_time']);

        // Fix any other old enum values that might exist
        DB::table('point_transactions')
            ->whereIn('type', ['task_done', 'completed_task', 'task_finished'])
            ->update(['type' => 'task_on_time']);

        // Fix invalid enum values in user_points table
        DB::table('user_points')
            ->where('transaction_type', 'task_completed')
            ->update(['transaction_type' => 'task_on_time']);

        DB::table('user_points')
            ->whereIn('transaction_type', ['task_done', 'completed_task', 'task_finished'])
            ->update(['transaction_type' => 'task_on_time']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Optionally revert back to old values if needed
        DB::table('point_transactions')
            ->where('type', 'task_on_time')
            ->update(['type' => 'task_completed']);

        DB::table('user_points')
            ->where('transaction_type', 'task_on_time')
            ->update(['transaction_type' => 'task_completed']);
    }
};
