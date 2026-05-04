<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The project_members table already exists.
 * This migration documents that the 'role' column is now governed
 * by the App\Enums\ProjectRole enum (manager, tech_lead, developer, qa, designer).
 *
 * We update the default to 'developer' (a valid ProjectRole value) and
 * add the new role values via a comment — no destructive changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_members', function (Blueprint $table) {
            // Change default to a valid ProjectRole value.
            // Existing rows with 'member' / 'lead' / 'reviewer' remain valid strings;
            // the enum cast on the model will handle unknown values gracefully.
            $table->string('role')->default('developer')->change();
        });
    }

    public function down(): void
    {
        Schema::table('project_members', function (Blueprint $table) {
            $table->string('role')->default('member')->change();
        });
    }
};
