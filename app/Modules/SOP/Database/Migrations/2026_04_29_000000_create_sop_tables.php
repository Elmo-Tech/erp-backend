<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('directives', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('phases');
            $table->json('target_permissions')->nullable();
            $table->boolean('is_recurring')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_recurring', 'created_at']);
            $table->index('created_by');
        });

        Schema::create('daily_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('directive_id')->constrained('directives')->cascadeOnDelete();
            $table->json('focus_items');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['user_id', 'submitted_at']);
            $table->index('directive_id');
        });

        foreach ($this->permissions() as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_checkins');
        Schema::dropIfExists('directives');

        Permission::whereIn('name', $this->permissions())->delete();
    }

    /**
     * @return array<int, string>
     */
    private function permissions(): array
    {
        return [
            'manage_sops',
            'view_morning_sop',
            'view_financial_stats',
            'view_project_timeline',
            'view_team_performance',
            'view_personal_tasks',
        ];
    }
};
