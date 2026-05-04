<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_task_deliverables', function (Blueprint $table) {
            $table->id();

            $table->foreignId('task_id')
                ->constrained('project_tasks')
                ->cascadeOnDelete();

            $table->string('title');
            $table->boolean('is_completed')->default(false);

            $table->timestamps();

            // Index
            $table->index('task_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_task_deliverables');
    }
};
