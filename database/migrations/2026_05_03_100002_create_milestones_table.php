<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();

            $table->string('title');

            // MilestoneStatus enum: pending | active | completed
            $table->string('status')->default('pending');

            // 0–100 completion percentage, updated programmatically
            $table->unsignedTinyInteger('progress_percentage')->default(0);

            // Optional link to a contract payment stage (future module)
            $table->unsignedBigInteger('payment_stage_id')->nullable()->index();

            // Optional link to an invoice once the milestone is billed
            $table->unsignedBigInteger('invoice_id')->nullable()->index();

            $table->timestamps();

            // Indexes
            $table->index('project_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milestones');
    }
};
