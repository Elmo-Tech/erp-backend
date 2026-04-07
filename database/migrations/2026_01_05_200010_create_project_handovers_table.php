<?php

use App\Traits\CreatedUpdatedByMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use CreatedUpdatedByMigration;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('project_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();

            // Handover details
            $table->foreignId('sales_rep_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_manager_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('handover_date');

            // Checklist
            $table->boolean('scope_confirmed')->default(false);
            $table->boolean('requirements_clear')->default(false);
            $table->boolean('timeline_agreed')->default(false);
            $table->boolean('budget_confirmed')->default(false);
            $table->boolean('risks_identified')->default(false);

            $table->text('notes')->nullable();
            $table->json('attachments')->nullable(); // [{name, path}]

            // Status
            $table->string('status')->default('pending'); // pending, completed, disputed
            $table->timestamp('completed_at')->nullable();

            $this->addAuditColumns($table);
            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->index('handover_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_handovers');
    }
};
