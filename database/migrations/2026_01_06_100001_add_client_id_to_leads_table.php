<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add client_id to leads for tracking converted leads
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Client ID - set when lead is converted to client
            $table->foreignId('client_id')->nullable()->after('id')->constrained('clients')->nullOnDelete();
            
            // Conversion tracking
            $table->timestamp('converted_at')->nullable()->after('loss_reason');
            $table->foreignId('converted_by')->nullable()->after('converted_at')->constrained('users')->nullOnDelete();
            
            // Index
            $table->index('client_id');
            $table->index('converted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropForeign(['converted_by']);
            $table->dropIndex(['client_id']);
            $table->dropIndex(['converted_at']);
            $table->dropColumn(['client_id', 'converted_at', 'converted_by']);
        });
    }
};
