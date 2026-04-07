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
        Schema::table('contracts', function (Blueprint $table) {
            // Add file_number field (unique, nullable)
            $table->string('file_number', 50)->unique()->nullable()->after('contract_number');

            // Add task_id foreign key (nullable)
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete()->after('quotation_id');

            // Add preparation_deadline (date, nullable)
            $table->date('preparation_deadline')->nullable()->after('template_id');

            // Add expiry_date (date, nullable) - calculated from expiryDays
            $table->date('expiry_date')->nullable()->after('end_date');

            // Add project_duration (string, nullable)
            $table->string('project_duration', 100)->nullable()->after('expiry_date');

            // Add deliverables (json, nullable)
            $table->json('deliverables')->nullable()->after('content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['task_id']);
            $table->dropUnique('contracts_file_number_unique');
            $table->dropColumn([
                'file_number',
                'task_id',
                'preparation_deadline',
                'expiry_date',
                'project_duration',
                'deliverables',
            ]);
        });
    }
};
