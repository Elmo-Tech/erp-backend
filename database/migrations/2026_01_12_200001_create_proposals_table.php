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
     * Creates proposals table - separate from quotations (technical proposal vs price quote)
     */
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->string('proposal_number')->unique();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()
                ->constrained('proposal_templates')->nullOnDelete();

            // Proposal details
            $table->string('title');
            $table->longText('content')->nullable(); // Rich content (Markdown/HTML)
            $table->string('file_path')->nullable(); // Uploaded PDF/PPT
            $table->string('file_name')->nullable();

            // Status tracking
            $table->string('status')->default('draft'); // draft, sent, viewed, approved, rejected, expired
            $table->date('submission_date')->nullable();
            $table->date('expiry_date')->nullable(); // Technical validity (default 10 days)
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();

            // Rejection handling
            $table->string('rejection_reason')->nullable();
            $table->text('rejection_notes')->nullable();

            // Fast track bypass
            $table->boolean('is_fast_track')->default(false);
            $table->string('fast_track_reason')->nullable();

            // Notes
            $table->text('internal_notes')->nullable();

            $this->addAuditColumns($table);
            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->index('expiry_date');
            $table->index('is_fast_track');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proposals');
    }
};
