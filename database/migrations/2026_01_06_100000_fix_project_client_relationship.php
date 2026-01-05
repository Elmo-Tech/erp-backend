<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Fix: Project should reference Client model, not User
     */
    public function up(): void
    {
        // Step 1: Drop the old foreign key constraint
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });

        // Step 2: Rename old column and add new one
        Schema::table('projects', function (Blueprint $table) {
            $table->renameColumn('client_id', 'old_user_client_id');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('id')->constrained('clients')->nullOnDelete();
        });

        // Step 3: Drop the temporary new_client_id column if exists
        if (Schema::hasColumn('projects', 'new_client_id')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropColumn('new_client_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropColumn('client_id');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->renameColumn('old_user_client_id', 'client_id');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('users')->restrictOnDelete();
        });
    }
};
