<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->uuid('session_id')->nullable()->after('user_id');
            $table->timestamp('last_heartbeat_at')->nullable()->after('start_time');
            $table->string('stop_reason', 32)->nullable()->after('duration_minutes');
            $table->unsignedBigInteger('resumed_from_id')->nullable()->after('stop_reason');
            $table->index('session_id');
            $table->index(['end_time', 'last_heartbeat_at'], 'time_entries_active_heartbeat_index');
            $table->index('resumed_from_id');
        });

        Schema::table('tasks', function (Blueprint $table): void {
            $table->text('hold_reason')->nullable()->after('internal_reviewer_notes');
        });

        DB::table('time_entries')
            ->whereNull('end_time')
            ->orderBy('id')
            ->chunkById(200, function ($entries): void {
                foreach ($entries as $entry) {
                    DB::table('time_entries')
                        ->where('id', $entry->id)
                        ->update([
                            'session_id' => (string) Str::uuid(),
                            'last_heartbeat_at' => now(),
                        ]);
                }
            });

        DB::table('lookup_options')->updateOrInsert(
            ['type' => 'task_status', 'value' => 'on_hold'],
            [
                'label' => 'On Hold',
                'color' => '#f59e0b',
                'icon' => null,
                'metadata' => json_encode(['is_system' => true]),
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        foreach (['review' => 4, 'testing' => 5, 'completed' => 6] as $value => $sortOrder) {
            DB::table('lookup_options')
                ->where('type', 'task_status')
                ->where('value', $value)
                ->update(['sort_order' => $sortOrder, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('lookup_options')
            ->where('type', 'task_status')
            ->where('value', 'on_hold')
            ->delete();

        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropColumn('hold_reason');
        });

        Schema::table('time_entries', function (Blueprint $table): void {
            $table->dropIndex('time_entries_active_heartbeat_index');
            $table->dropIndex(['session_id']);
            $table->dropIndex(['resumed_from_id']);
            $table->dropColumn([
                'session_id',
                'last_heartbeat_at',
                'stop_reason',
                'resumed_from_id',
            ]);
        });
    }
};
