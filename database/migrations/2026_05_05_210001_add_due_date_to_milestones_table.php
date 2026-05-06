<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `due_date` to milestones.
 *
 * Used as the fallback deadline for project tasks that do not have their own
 * due_date set. The Gamification Engine resolves: task.due_date → milestone.due_date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('title');
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            $table->dropIndex(['due_date']);
            $table->dropColumn('due_date');
        });
    }
};
