<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meetings') || ! Schema::hasColumn('meetings', 'subject_id')) {
            return;
        }

        Schema::table('meetings', function (Blueprint $table) {
            $table->dropIndex(['subject_type', 'subject_id']);
            $table->renameColumn('subject_id', 'model_id');
            $table->renameColumn('subject_type', 'model_type');
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->index(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('meetings') || ! Schema::hasColumn('meetings', 'model_id')) {
            return;
        }

        Schema::table('meetings', function (Blueprint $table) {
            $table->dropIndex(['model_type', 'model_id']);
            $table->renameColumn('model_id', 'subject_id');
            $table->renameColumn('model_type', 'subject_type');
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->index(['subject_type', 'subject_id']);
        });
    }
};
