<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('company_id')->nullable();
            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('target_type', 100);
            $table->uuid('target_id')->nullable();
            $table->string('event', 100);
            $table->jsonb('redacted_diff');
            $table->timestampTz('occurred_at');
            $table->index(['company_id','occurred_at']);
        });
        Schema::create('outbox_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('company_id')->nullable();
            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->string('aggregate_type', 100);
            $table->uuid('aggregate_id');
            $table->string('event_type', 100);
            $table->unsignedSmallInteger('event_version')->default(1);
            $table->jsonb('payload');
            $table->timestampTz('occurred_at');
            $table->timestampTz('published_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->index(['published_at','occurred_at']);
        });
        Schema::create('idempotency_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('route_signature',120);
            $table->string('idempotency_key',160);
            $table->char('request_hash',64);
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['company_id','actor_user_id','route_signature','idempotency_key'],'idempotent_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_requests');
        Schema::dropIfExists('outbox_events');
        Schema::dropIfExists('audit_logs');
    }
};
