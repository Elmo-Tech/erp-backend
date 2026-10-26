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
        Schema::create('device_access_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('external_id')->nullable();
            $table->string('device_employee_no')->nullable();
            $table->string('event_type')->nullable();
            $table->string('verify_mode')->nullable();
            $table->string('card_no')->nullable();
            $table->unsignedSmallInteger('door_no')->nullable();
            $table->boolean('is_authorized')->default(true);
            $table->dateTime('occurred_at');
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['device_id', 'occurred_at']);
            $table->index(['user_id', 'occurred_at']);
            $table->unique(['device_id', 'external_id'], 'device_access_events_device_external_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_access_events');
    }
};
