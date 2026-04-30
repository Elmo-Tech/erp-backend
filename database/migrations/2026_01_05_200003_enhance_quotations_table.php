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
        Schema::table('quotations', function (Blueprint $table) {
            // Quotation number
            $table->string('quotation_number', 50)->nullable()->unique()->after('id');

            // Template reference (foreign key will be added later)
            $table->unsignedBigInteger('template_id')->nullable()->after('client_id');

            // Lifecycle tracking
            $table->date('expiry_date')->nullable()->after('status');
            $table->timestamp('sent_at')->nullable()->after('expiry_date');
            $table->timestamp('viewed_at')->nullable()->after('sent_at');
            $table->string('rejection_reason')->nullable()->after('viewed_at'); // price, timeline, competitor, budget, scope, other
            $table->text('rejection_notes')->nullable()->after('rejection_reason');

            // Commission fields
            $table->string('commission_type')->nullable()->after('rejection_notes'); // markup, margin
            $table->decimal('commission_percentage', 5, 2)->default(0)->after('commission_type');
            $table->decimal('commission_amount', 15, 2)->default(0)->after('commission_percentage');
            $table->foreignId('commission_user_id')->nullable()->after('commission_amount')->constrained('users')->nullOnDelete();

            // Cost breakdown (hidden from client)
            $table->decimal('internal_cost', 15, 2)->default(0)->after('commission_user_id');
            $table->decimal('profit_margin', 15, 2)->default(0)->after('internal_cost');
            $table->decimal('override_price', 15, 2)->nullable()->after('profit_margin');
            $table->text('override_reason')->nullable()->after('override_price');

            // Notes
            $table->text('notes')->nullable()->after('override_reason');
            $table->text('terms_conditions')->nullable()->after('notes');

            // Indexes
            $table->index('expiry_date');
            $table->index('sent_at');
            $table->index('commission_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(['commission_user_id']);

            $table->dropIndex(['expiry_date']);
            $table->dropIndex(['sent_at']);
            $table->dropIndex(['commission_user_id']);

            $table->dropColumn([
                'quotation_number',
                'template_id',
                'expiry_date',
                'sent_at',
                'viewed_at',
                'rejection_reason',
                'rejection_notes',
                'commission_type',
                'commission_percentage',
                'commission_amount',
                'commission_user_id',
                'internal_cost',
                'profit_margin',
                'override_price',
                'override_reason',
                'notes',
                'terms_conditions',
            ]);
        });
    }
};
