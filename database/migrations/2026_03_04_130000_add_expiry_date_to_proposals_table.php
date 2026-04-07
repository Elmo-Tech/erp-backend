<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Complementary migration to ensure expiry_date column exists in proposals table.
     * This ensures the column is present for Phase 2 proposal expiry functionality.
     */
    public function up(): void
    {
        // Check if proposals table exists and expiry_date column doesn't
        if (Schema::hasTable('proposals') && ! Schema::hasColumn('proposals', 'expiry_date')) {
            Schema::table('proposals', function (Blueprint $table) {
                $table->date('expiry_date')->nullable()->after('submission_date');

                // Add index for faster expiry queries
                $table->index('expiry_date');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a complementary migration - don't drop the column
        // as it may be needed by other migrations
    }
};
