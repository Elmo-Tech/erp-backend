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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('company');
            $table->string('display_name');
            $table->string('legal_name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('primary_email')->nullable()->unique();
            $table->string('primary_phone')->nullable();
            $table->string('secondary_phone')->nullable();
            $table->string('position')->nullable();
            $table->string('industry')->nullable();
            $table->string('website')->nullable();
            $table->string('tax_number')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('source')->nullable();
            $table->string('lifecycle_stage')->default('lead');
            $table->string('lead_temperature')->nullable();
            $table->decimal('expected_budget', 15, 2)->nullable();
            $table->date('expected_close_date')->nullable();
            $table->timestamp('became_client_at')->nullable();
            $table->foreignId('account_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // linked user account
            $this->addAuditColumns($table);
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('type');
            $table->index('lifecycle_stage');
            $table->index('status');
            $table->index('company_name');
            $table->index('account_manager_id');
        });

        // Update projects table to reference clients instead of users
        Schema::table('projects', function (Blueprint $table) {
            // Add new client_id column
            $table->foreignId('new_client_id')->nullable()->after('client_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('new_client_id');
        });

        Schema::dropIfExists('clients');
    }
};
