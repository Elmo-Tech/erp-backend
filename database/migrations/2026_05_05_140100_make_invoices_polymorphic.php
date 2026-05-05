<?php

use App\Modules\Project\Models\Project;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices') || Schema::hasColumn('invoices', 'model_id')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('model_id')->nullable()->after('project_id');
            $table->string('model_type')->nullable()->after('model_id');
        });

        DB::table('invoices')->update([
            'model_type' => Project::class,
            'model_id' => DB::raw('project_id'),
        ]);

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropIndex(['project_id']);
            $table->dropColumn('project_id');
            $table->index(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoices') || Schema::hasColumn('invoices', 'project_id')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        DB::table('invoices')
            ->where('model_type', Project::class)
            ->update(['project_id' => DB::raw('model_id')]);

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['model_type', 'model_id']);
            $table->dropColumn(['model_id', 'model_type']);
            $table->index('project_id');
        });
    }
};
