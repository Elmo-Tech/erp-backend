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
        Schema::table('invoices', function (Blueprint $table) {
            // Add contract relationship for milestone-based billing
            $table->foreignId('contract_id')->nullable()->after('project_id')->constrained()->cascadeOnDelete();

            // Add milestone name for tracking which milestone this invoice is for
            $table->string('milestone_name')->nullable()->after('contract_id');

            // Add sent_at timestamp for tracking when invoice was sent
            $table->timestamp('sent_at')->nullable()->after('paid_at');

            // Add index for performance
            $table->index('contract_id');
            $table->index('milestone_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['contract_id']);
            $table->dropIndex(['contract_id']);
            $table->dropIndex(['milestone_name']);
            $table->dropColumn(['contract_id', 'milestone_name', 'sent_at']);
        });
    }
};
