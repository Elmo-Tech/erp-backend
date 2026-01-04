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
     */
    public function up(): void
    {
        Schema::create('lead_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->string('title');
            $table->string('location'); // online, office, client_site
            $table->string('meeting_link', 500)->nullable();
            $table->timestamp('scheduled_at');
            $table->integer('duration_minutes')->default(60);
            $table->json('attendees')->nullable(); // [{user_id, name, role}]
            $table->text('minutes')->nullable(); // Meeting notes
            $table->text('outcome')->nullable();
            $table->string('google_calendar_event_id')->nullable();
            $this->addAuditColumns($table);
            $table->timestamps();

            // Indexes
            $table->index('scheduled_at');
            $table->index('location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_meetings');
    }
};
