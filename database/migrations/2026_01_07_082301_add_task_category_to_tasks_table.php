<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->enum('task_category', [
                'backend', 'frontend', 'api', 'database', 'ui', 'component', 
                'styling', 'design', 'prototype', 'wireframe', 'mobile', 
                'ios', 'android', 'react_native', 'deployment', 'infrastructure', 
                'ci_cd', 'monitoring', 'testing', 'automation', 'bug_fix', 
                'quality_assurance', 'server', 'general'
            ])->default('general')->after('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('task_category');
        });
    }
};