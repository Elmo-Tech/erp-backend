<?php

use App\Traits\CreatedUpdatedByMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use CreatedUpdatedByMigration;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('project_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('name');
            $table->string('type'); // file, link
            $table->string('file_path')->nullable(); // for uploaded files
            $table->string('url')->nullable(); // for external links
            $table->string('mime_type')->nullable();
            $table->bigInteger('size')->nullable(); // file size in bytes
            $table->text('description')->nullable();
            $table->string('category')->default('general'); // general, design, document, deliverable
            $table->boolean('is_client_visible')->default(false);
            $table->boolean('is_downloadable')->default(true);
            $table->json('visible_to_roles')->nullable(); // specific roles that can see this file
            $this->addAuditColumns($table);
            $table->timestamps();

            // Indexes
            $table->index('project_id');
            $table->index('category');
            $table->index('is_client_visible');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_files');
    }
};
