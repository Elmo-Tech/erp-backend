<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['proposals', 'quotations', 'contracts'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('file_name')->nullable()->after('file_path');
                $table->string('file_mime_type')->nullable()->after('file_name');
                $table->unsignedBigInteger('file_size')->nullable()->after('file_mime_type');
            });
        }
    }

    public function down(): void
    {
        foreach (['proposals', 'quotations', 'contracts'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['file_name', 'file_mime_type', 'file_size']);
            });
        }
    }
};
