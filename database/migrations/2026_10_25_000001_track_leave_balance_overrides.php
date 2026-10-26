<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_leave_balances', function (Blueprint $table): void {
            $table->boolean('is_custom')->default(false);
        });

        // Preserve existing allocations that differ from today's default.
        foreach (DB::table('leave_types')->get() as $type) {
            DB::table('employee_leave_balances')->where('leave_type_id', $type->id)
                ->where('allocated_days', '!=', $type->annual_allowance_days)
                ->update(['is_custom' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('employee_leave_balances', fn (Blueprint $table) => $table->dropColumn('is_custom'));
    }
};
