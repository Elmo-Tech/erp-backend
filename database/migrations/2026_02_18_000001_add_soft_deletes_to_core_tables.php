<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Add Soft Deletes to Core Business Tables
 *
 * This migration adds soft delete capability to critical business tables
 * to prevent permanent data loss and enable data recovery.
 *
 * Security: Soft deletes allow recovery of accidentally deleted records
 * and maintain audit trail integrity.
 */
return new class extends Migration
{
    /**
     * Tables that need soft deletes for data protection
     */
    protected array $tables = [
        'users',
        'projects',
        'tasks',
        'leads',
        'clients',
        'invoices',
        'quotations',
        'contracts',
        'proposals',
        'milestones',
        'activities',
        'task_deliverables',
        'change_requests',
        'handovers',
        'project_wikis',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'deleted_at')) {
                Schema::table($tableName, function (Blueprint $blueprint) {
                    $blueprint->softDeletes()->after('updated_at');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'deleted_at')) {
                Schema::table($tableName, function (Blueprint $blueprint) {
                    $blueprint->dropSoftDeletes();
                });
            }
        }
    }
};
