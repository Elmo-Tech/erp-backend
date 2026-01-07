<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For SQLite, we need to recreate the table without the old_user_client_id column
        if (DB::getDriverName() === 'sqlite') {
            // Create new table structure
            Schema::create('projects_new', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
                $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('status')->default('draft');
                $table->string('previous_status')->nullable();
                $table->decimal('budget', 10, 2);
                $table->date('deadline')->nullable();
                $table->timestamp('ui_approved_at')->nullable();
                $table->string('auto_approval_job_id')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });

            // Copy data from old table to new table (excluding old_user_client_id)
            DB::statement('INSERT INTO projects_new (id, client_id, quotation_id, title, description, status, previous_status, budget, deadline, ui_approved_at, auto_approval_job_id, created_by, updated_by, created_at, updated_at) 
                          SELECT id, client_id, quotation_id, title, description, status, previous_status, budget, deadline, ui_approved_at, auto_approval_job_id, created_by, updated_by, created_at, updated_at 
                          FROM projects');

            // Drop old table and rename new one
            Schema::drop('projects');
            Schema::rename('projects_new', 'projects');
        } else {
            // For other databases, simply drop the column
            Schema::table('projects', function (Blueprint $table) {
                $table->dropColumn('old_user_client_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back the old_user_client_id column
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedBigInteger('old_user_client_id')->nullable()->after('id');
        });
    }
};