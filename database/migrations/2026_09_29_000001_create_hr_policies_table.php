<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_policies', function (Blueprint $table) {
            $table->id();
            $table->json('settings');
            $table->date('effective_from');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['effective_from', 'id']);
        });

        $settings = [
            'workingDays' => ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday'],
            'workStartTime' => '09:00',
            'workEndTime' => '17:00',
            'dailyRateDivisor' => 30,
            'unpaidLeaveDailyRateMultiplier' => 1,
            'annualLeaveDays' => 21,
            'sickLeaveDays' => 10,
            'emergencyLeaveDays' => 6,
            'leaveWorkflow' => 'both',
            'monthlyPaidPermissionHours' => 6,
            'permissionOverageBehavior' => 'admin_decides',
            'lateDeductionReset' => 'monthly',
            'lateDeductionTiers' => [
                ['minimumMinutes' => 15, 'maximumMinutes' => 29, 'percentages' => [0, 20, 30, 50]],
                ['minimumMinutes' => 30, 'maximumMinutes' => 44, 'percentages' => [10, 20, 30, 50]],
                ['minimumMinutes' => 45, 'maximumMinutes' => 59, 'percentages' => [20, 30, 40, 50]],
                ['minimumMinutes' => 60, 'maximumMinutes' => null, 'percentages' => [30, 40, 50, 60]],
            ],
            'overtimeRequiresPreapproval' => true,
            'overtimeMultiplier' => 1.5,
            'advanceLimitSalaryPercentage' => 100,
            'advanceSettlement' => 'next_payroll',
            'allowAdvanceInstallments' => true,
            'payrollPaymentDay' => 1,
            'midMonthHirePaidDays' => 15,
            'taskSupervisorLimit' => 1,
        ];

        DB::table('hr_policies')->insert([
            'settings' => json_encode($settings, JSON_THROW_ON_ERROR),
            'effective_from' => today()->toDateString(),
            'created_by' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate([
            'name' => 'manage_hr_settings',
            'guard_name' => 'web',
        ]);

        Role::query()
            ->whereIn('name', ['super-admin', 'admin', 'manager'])
            ->each(function (Role $role) use ($permission): void {
                if (! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_policies');

        Permission::query()
            ->where('name', 'manage_hr_settings')
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
