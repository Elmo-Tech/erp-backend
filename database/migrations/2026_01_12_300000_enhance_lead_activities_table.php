<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Enhances lead_activities with status workflow and reminder system
     */
    public function up(): void
    {
        Schema::table('lead_activities', function (Blueprint $table) {
            // Activity Status (scheduled, completed, cancelled)
            $table->string('status')->default('scheduled')->after('type');

            // Cancellation reason
            $table->string('cancellation_reason')->nullable()->after('outcome');

            // Next action planning
            $table->string('next_action')->nullable()->after('cancellation_reason');
            $table->timestamp('reminder_at')->nullable()->after('next_action');

            // Reminder sent flag
            $table->boolean('reminder_sent')->default(false)->after('reminder_at');
            $table->boolean('outcome_reminder_sent')->default(false)->after('reminder_sent');

            // Indexes
            $table->index('status');
            $table->index('reminder_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_activities', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['reminder_at']);

            $table->dropColumn([
                'status',
                'cancellation_reason',
                'next_action',
                'reminder_at',
                'reminder_sent',
                'outcome_reminder_sent',
            ]);
        });
    }
};
