<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds new fields for Client & Project Lifecycle Blueprint
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Industry Sector for analytics
            $table->string('industry_sector')->nullable()->after('country');
            
            // Lead Temperature (Hot/Warm/Cold)
            $table->string('lead_temperature')->default('warm')->after('priority');
            
            // Tech Lead Reference for technical evaluation
            $table->foreignId('tech_lead_id')->nullable()->after('reference_id')
                ->constrained('users')->nullOnDelete();
            
            // Project name (can be modified later)
            $table->string('project_name')->nullable()->after('name');
            
            // Timezone offset (calculated from country)
            $table->string('timezone_offset', 10)->nullable()->after('country');
            
            // Indexes
            $table->index('industry_sector');
            $table->index('lead_temperature');
            $table->index('tech_lead_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['industry_sector']);
            $table->dropIndex(['lead_temperature']);
            $table->dropIndex(['tech_lead_id']);
            
            $table->dropForeign(['tech_lead_id']);
            
            $table->dropColumn([
                'industry_sector',
                'lead_temperature',
                'tech_lead_id',
                'project_name',
                'timezone_offset',
            ]);
        });
    }
};
