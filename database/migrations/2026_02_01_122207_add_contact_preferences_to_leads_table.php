<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds contact preferences JSON field to leads table
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->json('contact_preferences')->nullable()->after('lead_temperature');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('contact_preferences');
        });
    }
};
