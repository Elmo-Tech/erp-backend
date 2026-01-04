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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_number', 50)->unique();
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('contract_templates')->nullOnDelete();
            $table->string('title');
            $table->longText('content'); // Final contract text
            
            // Parties
            $table->json('party_one'); // {company, representative, title, id_number, address}
            $table->json('party_two'); // Client details
            
            // Financial
            $table->decimal('total_amount', 15, 2);
            $table->string('currency', 3)->default('SAR');
            $table->text('payment_terms')->nullable();
            
            // Dates
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('signed_by_client')->nullable();
            
            // Status
            $table->string('status')->default('draft'); // draft, pending_signature, signed, active, completed, terminated
            
            $this->addAuditColumns($table);
            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->index('start_date');
            $table->index('signed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
