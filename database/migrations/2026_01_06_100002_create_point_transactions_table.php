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
     * Create point_transactions table for tracking all point changes
     */
    public function up(): void
    {
        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('points'); // Can be positive or negative
            $table->string('type'); // task_completed, bug_found, late_delivery, code_review, innovation, attendance_bonus, etc.
            $table->string('source_type')->nullable(); // App\Models\Task, App\Models\CodeReview, etc.
            $table->unsignedBigInteger('source_id')->nullable();
            $table->text('reason')->nullable();
            $table->integer('balance_after'); // Running balance after this transaction
            $this->addAuditColumns($table);
            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('type');
            $table->index(['source_type', 'source_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('point_transactions');
    }
};
