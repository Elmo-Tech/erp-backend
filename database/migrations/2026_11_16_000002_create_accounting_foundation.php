<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fiscal_years', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->string('label', 40);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('state', 20)->default('draft');
            $table->timestampsTz();
            $table->unique(['company_id','label']);
            $table->unique(['company_id','id']);
        });
        Schema::create('accounting_periods', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('fiscal_year_id');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('state', 20)->default('open');
            $table->timestampTz('locked_at')->nullable();
            $table->timestampsTz();
            $table->foreign(['company_id','fiscal_year_id'])->references(['company_id','id'])->on('fiscal_years')->restrictOnDelete();
            $table->unique(['company_id','id']);
        });
        Schema::create('ledger_accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('code', 50);
            $table->string('name');
            $table->string('account_type', 40);
            $table->string('normal_balance', 6);
            $table->boolean('active')->default(true);
            $table->timestampsTz();
            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->unique(['company_id','code']);
            $table->unique(['company_id','id']);
        });
        Schema::create('journal_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('period_id');
            $table->date('entry_date');
            $table->string('status', 20)->default('draft');
            $table->string('description')->nullable();
            $table->timestampTz('posted_at')->nullable();
            $table->timestampsTz();
            $table->foreign(['company_id','period_id'])->references(['company_id','id'])->on('accounting_periods')->restrictOnDelete();
            $table->unique(['company_id','id']);
        });
        Schema::create('journal_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('entry_id');
            $table->uuid('account_id');
            $table->unsignedInteger('line_no');
            $table->decimal('debit_base', 20, 4)->default(0);
            $table->decimal('credit_base', 20, 4)->default(0);
            $table->char('transaction_currency', 3)->nullable();
            $table->decimal('amount_currency', 20, 4)->nullable();
            $table->decimal('fx_rate_snapshot', 24, 12)->nullable();
            $table->foreign(['company_id','entry_id'])->references(['company_id','id'])->on('journal_entries')->restrictOnDelete();
            $table->foreign(['company_id','account_id'])->references(['company_id','id'])->on('ledger_accounts')->restrictOnDelete();
            $table->foreign('transaction_currency')->references('code')->on('currencies');
            $table->unique(['entry_id','line_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('ledger_accounts');
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('fiscal_years');
    }
};
