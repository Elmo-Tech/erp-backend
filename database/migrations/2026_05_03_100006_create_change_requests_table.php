<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();

            $table->string('title');
            $table->text('description');

            // Quoted price for the change request
            $table->decimal('price', 15, 2)->default(0);

            // ChangeRequestStatus enum: draft | sent_to_client | approved | rejected
            $table->string('status')->default('draft');

            $table->timestamps();

            // Indexes
            $table->index('project_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_requests');
    }
};
