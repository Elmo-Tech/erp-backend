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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('stage')->default('new_lead');
            $table->string('status')->default('active');
            $table->string('lead_temperature')->nullable();
            $table->string('source')->nullable();
            $table->decimal('expected_budget', 15, 2)->nullable();
            $table->date('expected_close_date')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $this->addAuditColumns($table);
            $table->timestamps();
            $table->softDeletes();

            $table->index('stage');
            $table->index('status');
            $table->index('assigned_to');
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
