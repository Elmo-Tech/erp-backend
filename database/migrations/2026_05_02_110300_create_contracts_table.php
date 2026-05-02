<?php

use App\Traits\CreatedUpdatedByMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use CreatedUpdatedByMigration;

    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();

            // Conversion Engine: nullable FKs allow transfer from Lead → Client/Project
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();

            // Core fields
            $table->string('status')->default('draft');
            $table->string('creation_type'); // task | direct_upload | skipped
            $table->string('skip_reason')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_path')->nullable();

            // Contract-specific fields
            $table->string('contract_model')->default('standard'); // standard | saas_subscription
            $table->unsignedInteger('development_days')->nullable();
            $table->unsignedInteger('review_days')->nullable();
            $table->decimal('delay_penalty', 15, 2)->nullable();
            $table->boolean('source_code_handover')->default(false);

            // SaaS / recurring billing fields
            $table->decimal('setup_fee', 15, 2)->nullable();
            $table->decimal('recurring_fee', 15, 2)->nullable();
            $table->string('billing_cycle')->nullable(); // monthly | quarterly | annually

            $table->json('payment_stages')->nullable(); // Milestone-based payment breakdown

            $this->addAuditColumns($table);
            $table->timestamps();
            $table->softDeletes();

            $table->index('lead_id');
            $table->index('client_id');
            $table->index('project_id');
            $table->index('status');
            $table->index('contract_model');
            $table->index('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
