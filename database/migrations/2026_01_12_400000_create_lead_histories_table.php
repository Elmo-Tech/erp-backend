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
     * Creates audit trail table for leads, proposals, and quotations
     */
    public function up(): void
    {
        Schema::create('lead_histories', function (Blueprint $table) {
            $table->id();
            
            // Polymorphic relation (Lead, Proposal, Quotation)
            $table->morphs('auditable');
            
            // Action category
            $table->string('action_type'); // status_change, data_edit, upload, activity_log
            
            // Field tracking
            $table->string('field_name')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            
            // Additional context
            $table->string('description')->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable(); // Extra data like file info, etc.
            
            // Who made the change
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            
            // IP and User Agent for security audit
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            
            $table->timestamps();

            // Indexes
            $table->index('action_type');
            $table->index('field_name');
            $table->index('changed_by');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_histories');
    }
};
