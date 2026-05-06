<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')
                ->constrained('finance_invoices')
                ->cascadeOnDelete();
            $table->string('description');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);   // quantity * unit_price
            $table->timestamps();

            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_invoice_items');
    }
};
