<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('google_drive_folder_id')->nullable()->after('auto_approval_job_id');
            $table->string('google_drive_folder_name')->nullable()->after('google_drive_folder_id');
            $table->string('google_drive_folder_url')->nullable()->after('google_drive_folder_name');
        });

        Schema::create('google_drive_connections', function (Blueprint $table) {
            $table->id();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_drive_connections');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'google_drive_folder_id',
                'google_drive_folder_name',
                'google_drive_folder_url',
            ]);
        });
    }
};
