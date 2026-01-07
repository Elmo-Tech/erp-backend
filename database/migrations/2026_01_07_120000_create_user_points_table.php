<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Traits\CreatedUpdatedByMigration;

return new class extends Migration
{
    use CreatedUpdatedByMigration;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('points_change'); // Can be positive or negative
            $table->integer('points_before'); // Points before this change
            $table->integer('points_after'); // Points after this change
            $table->string('reason'); // Reason for the change
            $table->enum('type', ['manual', 'automatic'])->default('manual');
            $table->timestamps();
            
            // Add created_by and updated_by columns
            $this->addCreatedUpdatedByColumns($table);
            
            // Indexes
            $table->index(['user_id', 'created_at']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_points');
    }
};