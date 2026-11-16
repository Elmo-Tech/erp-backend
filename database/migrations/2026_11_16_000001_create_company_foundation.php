<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table): void {
            $table->char('code', 3)->primary();
            $table->string('name', 100);
            $table->unsignedTinyInteger('minor_units')->default(2);
            $table->boolean('active')->default(true);
        });
        Schema::create('companies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 20)->unique();
            $table->string('legal_name');
            $table->char('country_code', 2);
            $table->char('base_currency_code', 3);
            $table->foreign('base_currency_code')->references('code')->on('currencies');
            $table->string('timezone', 80);
            $table->string('status', 20)->default('inactive');
            $table->timestampsTz();
        });
        Schema::create('company_memberships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->uuid('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestampsTz();
            $table->unique(['user_id', 'company_id']);
            $table->unique(['company_id', 'id']);
        });
        Schema::create('company_role_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('membership_id');
            $table->string('role_key', 80);
            $table->timestampsTz();
            $table->foreign(['company_id', 'membership_id'])->references(['company_id', 'id'])->on('company_memberships')->restrictOnDelete();
            $table->unique(['company_id', 'membership_id', 'role_key'], 'member_company_role_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_role_assignments');
        Schema::dropIfExists('company_memberships');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('currencies');
    }
};
