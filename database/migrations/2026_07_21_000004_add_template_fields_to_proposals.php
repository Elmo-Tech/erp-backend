<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->string('proposal_source')->nullable()->after('creation_type');
            $table->unsignedTinyInteger('template_id')->nullable()->after('proposal_source');
            $table->date('validity_date')->nullable()->after('template_id');
            $table->text('notes')->nullable()->after('validity_date');
        });
    }

    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropColumn(['proposal_source', 'template_id', 'validity_date', 'notes']);
        });
    }
};
