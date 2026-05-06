<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rebuilds the attendances table to align with Phase 3 HR schema.
 *
 * The original migration (2026_01_01_100001) created a minimal table.
 * This migration adds the missing columns required by the HR engine:
 *   - status enum (present | absent | late | excused)
 *   - total_worked_minutes
 *   - soft deletes
 *   - is_late, deduction_minutes, used_grace_day, expected_clock_in/out, notes
 *     (these were already in the Attendance model but missing from the schema)
 *
 * We use addColumn guards so this is safe to run even if columns already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendances')) {
            return;
        }

        Schema::table('attendances', function (Blueprint $table) {
            // Status enum — the canonical HR status for the day
            if (! Schema::hasColumn('attendances', 'status')) {
                $table->enum('status', ['present', 'absent', 'late', 'excused'])
                    ->default('present')
                    ->after('clock_out');
            }

            // Total worked minutes derived from time entries at check-out
            if (! Schema::hasColumn('attendances', 'total_worked_minutes')) {
                $table->unsignedInteger('total_worked_minutes')
                    ->default(0)
                    ->after('status');
            }

            // Deduction tracking (already in model, ensure column exists)
            if (! Schema::hasColumn('attendances', 'deduction_minutes')) {
                $table->unsignedInteger('deduction_minutes')
                    ->default(0)
                    ->after('idle_minutes');
            }

            if (! Schema::hasColumn('attendances', 'is_late')) {
                $table->boolean('is_late')->default(false)->after('deduction_minutes');
            }

            if (! Schema::hasColumn('attendances', 'used_grace_day')) {
                $table->boolean('used_grace_day')->default(false)->after('is_late');
            }

            if (! Schema::hasColumn('attendances', 'expected_clock_in')) {
                $table->string('expected_clock_in', 8)->nullable()->after('used_grace_day');
            }

            if (! Schema::hasColumn('attendances', 'expected_clock_out')) {
                $table->string('expected_clock_out', 8)->nullable()->after('expected_clock_in');
            }

            if (! Schema::hasColumn('attendances', 'notes')) {
                $table->text('notes')->nullable()->after('expected_clock_out');
            }

            // Soft deletes
            if (! Schema::hasColumn('attendances', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('attendances')) {
            return;
        }

        Schema::table('attendances', function (Blueprint $table) {
            $columns = [
                'status', 'total_worked_minutes', 'deduction_minutes',
                'is_late', 'used_grace_day', 'expected_clock_in',
                'expected_clock_out', 'notes', 'deleted_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('attendances', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
