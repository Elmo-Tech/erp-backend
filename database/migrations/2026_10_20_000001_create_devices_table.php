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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('vendor')->default('hikvision');
            $table->string('model')->nullable();
            $table->string('location')->nullable();
            $table->string('ip_address');
            $table->unsignedSmallInteger('port')->default(80);
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->boolean('verify_tls')->default(false);
            $table->string('webhook_token', 64)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_sync')->default(true);
            $table->unsignedInteger('sync_interval_minutes')->default(5);
            $table->timestamp('last_event_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_status')->nullable();
            $table->text('last_error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['vendor', 'ip_address']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
