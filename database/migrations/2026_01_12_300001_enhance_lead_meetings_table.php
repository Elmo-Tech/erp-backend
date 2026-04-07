<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Enhances lead_meetings with status, attachments, and client attendees
     */
    public function up(): void
    {
        Schema::table('lead_meetings', function (Blueprint $table) {
            // Meeting Status
            $table->string('status')->default('scheduled')->after('location');

            // Client attendees (separate from internal)
            $table->json('client_attendees')->nullable()->after('attendees');

            // Attachments (recordings, whiteboard photos)
            $table->json('attachments')->nullable()->after('outcome');

            // Cancellation
            $table->string('cancellation_reason')->nullable()->after('attachments');

            // Reminder flags
            $table->boolean('reminder_sent')->default(false)->after('cancellation_reason');
            $table->boolean('outcome_reminder_sent')->default(false)->after('reminder_sent');

            // Indexes
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_meetings', function (Blueprint $table) {
            $table->dropIndex(['status']);

            $table->dropColumn([
                'status',
                'client_attendees',
                'attachments',
                'cancellation_reason',
                'reminder_sent',
                'outcome_reminder_sent',
            ]);
        });
    }
};
