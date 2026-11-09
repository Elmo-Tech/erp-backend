<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('task_project_type', function (Blueprint $table): void {
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lookup_option_id')->constrained('lookup_options')->restrictOnDelete();
            $table->primary(['task_id', 'lookup_option_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_project_type');
    }
};
