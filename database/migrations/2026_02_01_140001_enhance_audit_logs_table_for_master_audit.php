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
        Schema::table('audit_logs', function (Blueprint $table) {
            // Add event type for categorizing different types of system events
            $table->string('event_type', 50)->after('action')->index();
            
            // Add severity level for filtering critical events
            $table->enum('severity', ['info', 'warning', 'critical'])->default('info')->after('event_type')->index();
            
            // Add description field for human-readable event description
            $table->text('description')->nullable()->after('severity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['event_type']);
            $table->dropIndex(['severity']);
            $table->dropColumn(['event_type', 'severity', 'description']);
        });
    }
};