<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('name');                                          // e.g. "GitHub Copilot"
            $table->string('provider')->nullable();                          // e.g. "GitHub"
            $table->enum('billing_cycle', ['monthly', 'yearly'])->default('monthly');
            $table->decimal('cost', 15, 2);
            $table->string('currency', 3)->default('USD');
            $table->date('next_billing_date');
            $table->enum('status', ['active', 'cancelled'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('next_billing_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
