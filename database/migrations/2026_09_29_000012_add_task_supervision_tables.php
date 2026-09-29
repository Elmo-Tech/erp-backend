<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->foreignId('supervisor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->index()
                ->after('assigned_to');
        });

        Schema::create('task_help_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('message', 2000);
            $table->enum('status', ['open', 'acknowledged', 'resolved', 'cancelled'])->default('open');
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'requested_by', 'status']);
            $table->index(['task_id', 'status', 'created_at']);
        });

        Schema::create('task_supervision_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('supervisor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('help_request_id')->nullable()->constrained('task_help_requests')->nullOnDelete();
            $table->uuid('session_id')->unique();
            $table->text('notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('stop_reason', 1000)->nullable();
            $table->timestamps();

            $table->index(['supervisor_id', 'ended_at']);
            $table->index(['task_id', 'started_at']);
            $table->index('help_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_supervision_sessions');
        Schema::dropIfExists('task_help_requests');

        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('supervisor_id');
        });
    }
};
