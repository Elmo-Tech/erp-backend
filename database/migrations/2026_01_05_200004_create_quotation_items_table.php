<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->string('resource_type'); // backend, frontend, ui_ux, mobile, devops, qa, pm
            $table->string('description');
            $table->decimal('estimated_hours', 8, 2);
            $table->decimal('hourly_rate', 10, 2);
            $table->decimal('internal_hourly_cost', 10, 2)->default(0); // Hidden from client
            $table->decimal('subtotal', 15, 2);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Indexes
            $table->index('resource_type');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
    }
};
