<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Links quotations to proposals and adds sales commission fields
     */
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            // Link to proposal (optional for fast track)
            $table->foreignId('proposal_id')->nullable()->after('lead_id')
                ->constrained()->nullOnDelete();

            // Scope description for fast track (when no proposal)
            $table->text('scope_description')->nullable()->after('proposal_id');

            // Sales commission (separate from existing commission fields)
            $table->decimal('sales_commission_percentage', 5, 2)->default(0)->after('commission_amount');
            $table->decimal('sales_commission_amount', 15, 2)->default(0)->after('sales_commission_percentage');

            // Index
            $table->index('proposal_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropIndex(['proposal_id']);
            $table->dropForeign(['proposal_id']);

            $table->dropColumn([
                'proposal_id',
                'scope_description',
                'sales_commission_percentage',
                'sales_commission_amount',
            ]);
        });
    }
};
