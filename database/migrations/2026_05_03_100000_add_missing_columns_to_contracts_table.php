<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the remaining contract fields that the ContractDirectUploadTab
 * schema references but were missing from the initial migration.
 *
 * Standard model additions:
 *   - testing_days          (int)
 *   - execution_start_date  (date)
 *   - working_days_per_week (int)
 *   - currency              (string)
 *   - total_amount          (decimal)
 *
 * SaaS model additions:
 *   - subscription_start_date (date)
 *   - apply_promo             (boolean)
 *   - promo_discount          (decimal)
 *   - addons                  (json)
 *
 * Notes field (shared):
 *   - notes                   (text)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // ── Standard model ────────────────────────────────────────────────
            $table->unsignedInteger('testing_days')->nullable()->after('review_days');
            $table->date('execution_start_date')->nullable()->after('source_code_handover');
            $table->unsignedTinyInteger('working_days_per_week')->nullable()->after('execution_start_date');
            $table->string('currency', 10)->nullable()->after('working_days_per_week');
            $table->decimal('total_amount', 15, 2)->nullable()->after('currency');

            // ── SaaS model ────────────────────────────────────────────────────
            $table->date('subscription_start_date')->nullable()->after('billing_cycle');
            $table->boolean('apply_promo')->default(false)->after('subscription_start_date');
            $table->decimal('promo_discount', 5, 2)->nullable()->after('apply_promo');
            $table->json('addons')->nullable()->after('promo_discount');

            // ── Shared ────────────────────────────────────────────────────────
            $table->text('notes')->nullable()->after('addons');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn([
                'testing_days',
                'execution_start_date',
                'working_days_per_week',
                'currency',
                'total_amount',
                'subscription_start_date',
                'apply_promo',
                'promo_discount',
                'addons',
                'notes',
            ]);
        });
    }
};
