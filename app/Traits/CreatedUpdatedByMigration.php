<?php

namespace App\Traits;

use Illuminate\Database\Schema\Blueprint;

trait CreatedUpdatedByMigration
{
    /**
     * Add created_by and updated_by columns to a table
     */
    public function addAuditColumns(Blueprint $table): void
    {
        $table->foreignId('created_by')
            ->nullable()
            ->constrained('users')
            ->nullOnDelete();

        $table->foreignId('updated_by')
            ->nullable()
            ->constrained('users')
            ->nullOnDelete();
    }

    /**
     * Drop created_by and updated_by columns from a table
     */
    public function dropAuditColumns(Blueprint $table): void
    {
        $table->dropForeign(['created_by']);
        $table->dropForeign(['updated_by']);
        $table->dropColumn(['created_by', 'updated_by']);
    }
}
