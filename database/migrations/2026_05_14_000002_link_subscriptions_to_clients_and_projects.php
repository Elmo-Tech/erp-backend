<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('id')->constrained('clients')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->after('client_id')->constrained('projects')->nullOnDelete();
            $table->string('billing_cycle', 50)->default('monthly')->change();

            $table->index('client_id');
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['client_id']);
            $table->dropIndex(['project_id']);
            $table->dropForeign(['client_id']);
            $table->dropForeign(['project_id']);
            $table->dropColumn(['client_id', 'project_id']);
            $table->enum('billing_cycle', ['monthly', 'yearly'])->default('monthly')->change();
        });
    }
};
