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
        Schema::create('proposal_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // saas, custom, mobile, maintenance, consultation
            $table->longText('content'); // Rich HTML/Markdown with placeholders
            $table->json('placeholders')->nullable(); // [{key: 'client_name', label: 'اسم العميل'}]
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
        Schema::dropIfExists('proposal_templates');
    }
};
