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
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name');                                    // e.g. "Morning Shift", "Night Shift"
            $table->time('start_time');                                // e.g. "09:00:00"
            $table->time('end_time');                                  // e.g. "17:00:00"
            $table->json('working_days');                              // e.g. ["Sun","Mon","Tue","Wed","Thu"]
            $table->unsignedSmallInteger('grace_period_minutes')->default(15);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
