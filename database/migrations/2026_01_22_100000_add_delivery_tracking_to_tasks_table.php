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
        Schema::table('tasks', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('updated_at');
            $table->integer('delay_days')->nullable()->after('completed_at')->comment('Days delayed from due date (negative if early)');
            
            // Add index for completed_at for reporting queries
            $table->index('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['completed_at']);
            $table->dropColumn(['completed_at', 'delay_days']);
        });
    }
};