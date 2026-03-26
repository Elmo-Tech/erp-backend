<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds feedback and team_lead_id columns to lead_meetings table
     */
    public function up(): void
    {
        Schema::table('lead_meetings', function (Blueprint $table) {
            // Feedback column for meeting feedback
            $table->text('feedback')->nullable()->after('outcome');
            
            // Team lead ID - references users table
            $table->foreignId('team_lead_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->after('feedback');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_meetings', function (Blueprint $table) {
            $table->dropForeign(['team_lead_id']);
            $table->dropColumn(['feedback', 'team_lead_id']);
        });
    }
};
