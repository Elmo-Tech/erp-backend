<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->enum('event_type', ['meeting', 'reminder', 'custom'])->default('custom');
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('start_time');
            $table->index('end_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
