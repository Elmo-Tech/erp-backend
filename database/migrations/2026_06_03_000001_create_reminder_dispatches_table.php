<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('reminder_key')->unique();
            $table->string('reminder_type');
            $table->nullableMorphs('remindable');
            $table->nullableMorphs('notifiable');
            $table->string('channel')->default('mail');
            $table->date('scheduled_for')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['reminder_type', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_dispatches');
    }
};
