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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->timestamp('clock_in');
            $table->timestamp('clock_out')->nullable();
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('idle_minutes')->default(0);
            $table->timestamps();

            // Unique constraint on user_id and date
            $table->unique(['user_id', 'date'], 'attendances_user_date_unique');

            // Index on date for queries
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
