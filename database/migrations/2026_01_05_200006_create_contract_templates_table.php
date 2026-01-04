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
        Schema::create('contract_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // development, maintenance, sla, nda, consultation
            $table->longText('content'); // Legal text with placeholders
            $table->json('placeholders')->nullable();
            $table->boolean('is_active')->default(true);
            $this->addAuditColumns($table);
            $table->timestamps();

            // Indexes
            $table->index('type');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_templates');
    }
};
