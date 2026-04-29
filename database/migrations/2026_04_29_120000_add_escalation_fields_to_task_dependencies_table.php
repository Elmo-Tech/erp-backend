<?php

use App\Modules\User\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_dependencies', function (Blueprint $table) {
            $table->boolean('is_escalated')->default(false)->after('lag_days')->index();
            $table->foreignIdFor(User::class, 'escalated_by')->nullable()->after('is_escalated')->constrained('users')->nullOnDelete();
            $table->timestamp('escalated_at')->nullable()->after('escalated_by');
            $table->timestamp('resolved_at')->nullable()->after('escalated_at');

            $table->index(['is_escalated', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::table('task_dependencies', function (Blueprint $table) {
            $table->dropIndex(['is_escalated', 'resolved_at']);
            $table->dropConstrainedForeignId('escalated_by');
            $table->dropColumn(['is_escalated', 'escalated_at', 'resolved_at']);
        });
    }
};
