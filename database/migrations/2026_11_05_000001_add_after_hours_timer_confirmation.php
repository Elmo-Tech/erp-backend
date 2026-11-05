<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->timestamp('after_hours_prompt_at')->nullable();
            $table->timestamp('after_hours_confirmed_at')->nullable();
            $table->unsignedSmallInteger('after_hours_grace_minutes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->dropColumn(['after_hours_prompt_at', 'after_hours_confirmed_at', 'after_hours_grace_minutes']);
        });
    }
};
