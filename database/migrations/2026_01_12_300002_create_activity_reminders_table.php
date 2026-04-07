<?php

use App\Traits\CreatedUpdatedByMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use CreatedUpdatedByMigration;

    /**
     * Run the migrations.
     * Creates a dedicated reminders table for flexible reminder scheduling
     */
    public function up(): void
    {
        Schema::create('activity_reminders', function (Blueprint $table) {
            $table->id();

            // Polymorphic relation (can remind for activities, meetings, proposals, quotations)
            $table->morphs('remindable');

            // Who to remind
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Reminder details
            $table->string('type'); // before_event, after_event, custom
            $table->integer('minutes_offset')->default(15); // Minutes before/after
            $table->timestamp('remind_at');
            $table->string('channel')->default('database'); // database, mail, both

            // Status
            $table->boolean('is_sent')->default(false);
            $table->timestamp('sent_at')->nullable();

            // Message
            $table->string('title');
            $table->text('message')->nullable();

            $this->addAuditColumns($table);
            $table->timestamps();

            // Indexes (morphs already creates index for remindable)
            $table->index('remind_at');
            $table->index('is_sent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_reminders');
    }
};
