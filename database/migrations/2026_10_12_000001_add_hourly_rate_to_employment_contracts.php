<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employment_contracts', function (Blueprint $table): void {
            $table->decimal('hourly_rate', 12, 2)->nullable();
        });

        DB::table('employment_contracts')->where('status', 'active')->update([
            'hourly_rate' => DB::raw('(SELECT hourly_rate FROM users WHERE users.id = employment_contracts.employee_id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('employment_contracts', function (Blueprint $table): void {
            $table->dropColumn('hourly_rate');
        });
    }
};
