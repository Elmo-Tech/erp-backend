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
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->json('estimations');
            $table->decimal('hourly_rate', 10, 2);
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->decimal('final_price', 15, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('draft');
            $this->addAuditColumns($table);
            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->index('client_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
