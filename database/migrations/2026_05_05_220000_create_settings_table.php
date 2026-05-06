<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generic key-value settings store.
 *
 * Distinct from the dictionary tables (name/slug/is_active) which hold
 * enumerable lists. This table holds singleton scalar configuration values
 * that must be controllable at runtime without a code deploy.
 *
 * Example rows:
 *   key = 'gamification_point_value'  value = '50'
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
