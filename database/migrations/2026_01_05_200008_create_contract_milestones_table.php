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
        Schema::create('contract_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->decimal('percentage', 5, 2); // % of total
            $table->date('due_date')->nullable();

            // Event-based trigger
            $table->string('trigger_type')->default('date'); // immediate, project_status, deliverable, date
            $table->string('trigger_value', 100)->nullable(); // e.g., 'design_approved', 'beta_delivered'

            // Status
            $table->string('status')->default('pending'); // pending, due, invoiced, paid, overdue
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->index('due_date');
            $table->index('trigger_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_milestones');
    }
};
