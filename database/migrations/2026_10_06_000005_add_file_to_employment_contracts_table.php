<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employment_contracts', function (Blueprint $table) {
            $table->string('disk', 20)->nullable()->after('notes');
            $table->string('file_path', 500)->nullable()->after('disk');
            $table->string('original_filename', 255)->nullable()->after('file_path');
            $table->string('mime_type', 100)->nullable()->after('original_filename');
            $table->unsignedBigInteger('file_size')->nullable()->after('mime_type');
            $table->foreignId('file_uploaded_by')->nullable()->after('file_size')->constrained('users')->nullOnDelete();
            $table->timestamp('file_uploaded_at')->nullable()->after('file_uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::table('employment_contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('file_uploaded_by');
            $table->dropColumn(['disk', 'file_path', 'original_filename', 'mime_type', 'file_size', 'file_uploaded_at']);
        });
    }
};
