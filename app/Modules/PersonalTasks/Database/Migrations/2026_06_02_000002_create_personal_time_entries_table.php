<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personal_task_id')->constrained('personal_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->boolean('is_manual')->default(false);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('personal_task_id');
            $table->index(['user_id', 'end_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_time_entries');
    }
};
