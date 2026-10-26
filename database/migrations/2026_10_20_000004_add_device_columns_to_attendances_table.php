<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('source')->default('web')->after('date');
            $table->foreignId('device_id')->nullable()->after('source')->constrained('devices')->nullOnDelete();
            $table->string('external_id')->nullable()->after('device_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_no')->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['device_id']);
            $table->dropColumn(['source', 'device_id', 'external_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('employee_no');
        });
    }
};
