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
        Schema::table('leads', function (Blueprint $table) {
            // Reference tracking (polymorphic)
            $table->string('reference_type')->nullable()->after('source'); // sales_rep, client_referral, employee
            $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type');

            // Additional lead info
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('country', 100)->nullable()->after('company');
            $table->string('priority')->default('medium')->after('status'); // low, medium, high, urgent
            $table->decimal('expected_budget', 15, 2)->nullable()->after('priority');
            $table->date('expected_close_date')->nullable()->after('expected_budget');
            $table->timestamp('last_contact_at')->nullable()->after('expected_close_date');
            $table->timestamp('next_follow_up_at')->nullable()->after('last_contact_at');
            $table->text('notes')->nullable()->after('next_follow_up_at');
            $table->string('loss_reason')->nullable()->after('notes'); // price, timeline, competitor, budget, scope, other

            // Indexes
            $table->index('priority');
            $table->index('reference_type');
            $table->index(['reference_type', 'reference_id']);
            $table->index('next_follow_up_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['priority']);
            $table->dropIndex(['reference_type']);
            $table->dropIndex(['reference_type', 'reference_id']);
            $table->dropIndex(['next_follow_up_at']);

            $table->dropColumn([
                'reference_type',
                'reference_id',
                'phone',
                'country',
                'priority',
                'expected_budget',
                'expected_close_date',
                'last_contact_at',
                'next_follow_up_at',
                'notes',
                'loss_reason',
            ]);
        });
    }
};
