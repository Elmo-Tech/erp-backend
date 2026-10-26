<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_push_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('endpoint_hash', 64)->unique();
            $table->text('endpoint');
            $table->text('public_key');
            $table->text('auth_token');
            $table->timestamps();
        });
        Schema::table('users', fn (Blueprint $table) => $table->unsignedSmallInteger('timer_reminder_minutes')->default(15));
        Schema::table('time_entries', fn (Blueprint $table) => $table->timestamp('last_reminded_at')->nullable());
    }

    public function down(): void
    {
        Schema::dropIfExists('web_push_subscriptions');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('timer_reminder_minutes'));
        Schema::table('time_entries', fn (Blueprint $table) => $table->dropColumn('last_reminded_at'));
    }
};
