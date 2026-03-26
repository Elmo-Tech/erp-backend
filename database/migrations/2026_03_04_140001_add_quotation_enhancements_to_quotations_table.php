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
        Schema::table('quotations', function (Blueprint $table) {
            // File number for easier reference
            $table->string('file_number', 50)->nullable()->unique()->after('quotation_number');
            
            // Revision tracking
            $table->boolean('needs_revision')->default(false)->after('file_number');
            $table->text('revision_notes')->nullable()->after('needs_revision');
            
            // Task linkage for project-based quotations
            $table->foreignId('task_id')->nullable()->after('revision_notes')->constrained('tasks')->nullOnDelete();
            
            // Indexes
            $table->index('needs_revision');
            $table->index('task_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(['task_id']);
            
            $table->dropUnique('quotations_file_number_unique');
            $table->dropIndex(['needs_revision']);
            $table->dropIndex(['task_id']);
            
            $table->dropColumn([
                'file_number',
                'needs_revision',
                'revision_notes',
                'task_id',
            ]);
        });
    }
};
