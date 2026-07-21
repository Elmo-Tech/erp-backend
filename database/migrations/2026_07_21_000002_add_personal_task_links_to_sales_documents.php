<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['proposals', 'quotations', 'contracts'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('personal_task_id')
                    ->nullable()
                    ->after('assigned_to')
                    ->constrained('personal_tasks')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['proposals', 'quotations', 'contracts'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('personal_task_id');
            });
        }
    }
};
