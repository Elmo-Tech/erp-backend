<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Laravel's PostgreSQL enum is a varchar plus a CHECK constraint;
            // enum()->change() would generate invalid ALTER TYPE ... CHECK SQL.
            DB::statement('ALTER TABLE employee_leaves ALTER COLUMN status DROP NOT NULL, ALTER COLUMN status DROP DEFAULT');
            DB::table('employee_leaves')->where('status', 'pending')->update(['status' => null]);
            DB::statement('ALTER TABLE employee_leaves DROP CONSTRAINT IF EXISTS employee_leaves_status_check');
            DB::statement("ALTER TABLE employee_leaves ADD CONSTRAINT employee_leaves_status_check CHECK (status IN ('approved', 'rejected', 'cancelled'))");

            return;
        }

        // A submitted request has no decision until an authorized reviewer acts.
        Schema::table('employee_leaves', function (Blueprint $table): void {
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->nullable()->default(null)->change();
        });
        DB::table('employee_leaves')->where('status', 'pending')->update(['status' => null]);
        Schema::table('employee_leaves', function (Blueprint $table): void {
            $table->enum('status', ['approved', 'rejected', 'cancelled'])->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE employee_leaves DROP CONSTRAINT IF EXISTS employee_leaves_status_check');
            DB::statement("ALTER TABLE employee_leaves ADD CONSTRAINT employee_leaves_status_check CHECK (status IN ('pending', 'approved', 'rejected', 'cancelled'))");
            DB::table('employee_leaves')->whereNull('status')->update(['status' => 'pending']);
            DB::statement("ALTER TABLE employee_leaves ALTER COLUMN status SET DEFAULT 'pending', ALTER COLUMN status SET NOT NULL");

            return;
        }

        Schema::table('employee_leaves', function (Blueprint $table): void {
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->nullable()->default(null)->change();
        });
        DB::table('employee_leaves')->whereNull('status')->update(['status' => 'pending']);
        Schema::table('employee_leaves', function (Blueprint $table): void {
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->nullable(false)->default('pending')->change();
        });
    }
};
