<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_tasks', function (Blueprint $table) {
            $table->string('priority')->default('medium')->after('status');
            $table->index(['user_id', 'priority']);
        });

        Schema::table('project_tasks', function (Blueprint $table) {
            $table->string('priority')->default('medium')->after('status');
            $table->index(['assignee_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::table('personal_tasks', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'priority']);
            $table->dropColumn('priority');
        });

        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropIndex(['assignee_id', 'priority']);
            $table->dropColumn('priority');
        });
    }
};
