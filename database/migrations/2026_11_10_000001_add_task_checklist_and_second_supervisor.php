<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->foreignId('secondary_supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('description_checklist')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('secondary_supervisor_id');
            $table->dropColumn('description_checklist');
        });
    }
};
