<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deduction_types', function (Blueprint $table): void {
            $table->string('category', 50)->default('other');
        });
    }

    public function down(): void
    {
        Schema::table('deduction_types', function (Blueprint $table): void {
            $table->dropColumn('category');
        });
    }
};
