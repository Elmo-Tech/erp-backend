<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_invoices', function (Blueprint $table) {
            $table->string('invoice_type', 32)->default('one_time')->after('invoice_number');
            $table->string('billing_cycle', 50)->nullable()->after('invoice_type');
            $table->foreignId('subscription_id')
                ->nullable()
                ->after('billing_cycle')
                ->constrained('subscriptions')
                ->nullOnDelete();

            $table->index('invoice_type');
            $table->index('subscription_id');
        });
    }

    public function down(): void
    {
        Schema::table('finance_invoices', function (Blueprint $table) {
            $table->dropIndex(['invoice_type']);
            $table->dropIndex(['subscription_id']);
            $table->dropForeign(['subscription_id']);
            $table->dropColumn(['invoice_type', 'billing_cycle', 'subscription_id']);
        });
    }
};
