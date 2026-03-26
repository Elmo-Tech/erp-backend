<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds Phase 2 Priority 2 proposal enhancement fields:
     * - file_number (unique proposal file number)
     * - is_skipped (flag for skipped proposals)
     * - skip_reason (reason for skipping)
     * - task_id (link to tasks table)
     * Note: expiry_date already exists in the original table
     */
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            // Add file_number for unique proposal file identification
            // Format: PV-YEAR-0001 (e.g., PV-2026-0001)
            $table->string('file_number')->unique()->nullable()->after('proposal_number');
            
            // Add skip fields for fast-track/skipped proposals
            $table->boolean('is_skipped')->default(false)->after('is_fast_track');
            $table->text('skip_reason')->nullable()->after('is_skipped');
            
            // Add task relationship
            $table->foreignId('task_id')
                ->nullable()
                ->constrained('tasks')
                ->nullOnDelete()
                ->after('skip_reason');
            
            // Add index for faster queries
            $table->index('is_skipped');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            // Drop foreign key first, then the column
            $table->dropForeign(['task_id']);
            $table->dropIndex(['is_skipped']);
            $table->dropUnique('proposals_file_number_unique');
            $table->dropColumn([
                'file_number',
                'is_skipped',
                'skip_reason',
                'task_id',
            ]);
        });
    }
};
