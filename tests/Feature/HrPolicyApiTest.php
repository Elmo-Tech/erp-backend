<?php

namespace Tests\Feature;

use App\Models\HrPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class HrPolicyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_policy_is_returned_as_the_current_policy(): void
    {
        $user = $this->userWithHrSettingsPermission();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/hr/policies/current')
            ->assertOk()
            ->assertJsonPath('data.effectiveFrom', today()->toDateString())
            ->assertJsonPath('data.createdBy', null)
            ->assertJsonPath('data.settings.workingDays', ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday'])
            ->assertJsonPath('data.settings.lateDeductionTiers.3.maximumMinutes', null)
            ->assertJsonPath('data.settings.overtimeMultiplier', 1.5);

        $this->assertSame($this->defaultSettings(), HrPolicy::sole()->settings);
    }

    public function test_policy_endpoints_require_authentication_and_hr_settings_permission(): void
    {
        $this->getJson('/api/v1/hr/policies/current')->assertUnauthorized();

        $userWithoutPermission = User::factory()->create();

        $this->actingAs($userWithoutPermission, 'sanctum')
            ->getJson('/api/v1/hr/policies')
            ->assertForbidden();

        $this->actingAs($userWithoutPermission, 'sanctum')
            ->postJson('/api/v1/hr/policies', ['settings' => $this->defaultSettings()])
            ->assertForbidden();
    }

    public function test_policy_creation_validates_working_hours_and_late_deduction_tiers(): void
    {
        $user = $this->userWithHrSettingsPermission();
        $settings = $this->defaultSettings();
        $settings['workEndTime'] = '08:00';
        $settings['dailyRateDivisor'] = 0;
        $settings['advanceLimitSalaryPercentage'] = 101;
        $settings['lateDeductionTiers'][0] = [
            'minimumMinutes' => 30,
            'maximumMinutes' => 29,
            'percentages' => [0, 20, 30, 101],
        ];

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/hr/policies', [
                'settings' => $settings,
                'effectiveFrom' => 'not-a-date',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'settings.workEndTime',
                'settings.dailyRateDivisor',
                'settings.advanceLimitSalaryPercentage',
                'settings.lateDeductionTiers.0.maximumMinutes',
                'settings.lateDeductionTiers.0.percentages.3',
                'effectiveFrom',
            ]);

        $this->assertDatabaseCount('hr_policies', 1);
    }

    public function test_policy_requires_exactly_one_task_supervisor(): void
    {
        $user = $this->userWithHrSettingsPermission();
        $settings = $this->defaultSettings();
        $settings['taskSupervisorLimit'] = 2;

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/hr/policies', ['settings' => $settings])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('settings.taskSupervisorLimit');

        $this->assertDatabaseCount('hr_policies', 1);
    }

    public function test_creating_a_future_policy_preserves_prior_versions_and_resolves_as_of_dates(): void
    {
        $user = $this->userWithHrSettingsPermission();
        $defaultPolicy = HrPolicy::sole();
        $settings = $this->defaultSettings();
        $settings['annualLeaveDays'] = 25;

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/hr/policies', [
                'settings' => $settings,
                'effectiveFrom' => '2030-01-01',
                'notes' => 'Expanded annual leave allocation.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.settings.annualLeaveDays', 25)
            ->assertJsonPath('data.effectiveFrom', '2030-01-01')
            ->assertJsonPath('data.createdBy', $user->id)
            ->assertJsonPath('data.notes', 'Expanded annual leave allocation.');

        $futurePolicyId = $response->json('data.policyId');

        $this->assertDatabaseCount('hr_policies', 2);
        $this->assertSame($this->defaultSettings(), $defaultPolicy->fresh()->settings);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/hr/policies/current?date='.$defaultPolicy->effective_from->toDateString())
            ->assertOk()
            ->assertJsonPath('data.policyId', $defaultPolicy->id)
            ->assertJsonPath('data.settings.annualLeaveDays', 21);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/hr/policies/current?date=2030-01-01')
            ->assertOk()
            ->assertJsonPath('data.policyId', $futurePolicyId);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/hr/policies')
            ->assertOk()
            ->assertJsonPath('data.0.policyId', $futurePolicyId);
    }

    private function userWithHrSettingsPermission(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('manage_hr_settings', 'web'));

        return $user;
    }

    private function defaultSettings(): array
    {
        return [
            'workingDays' => ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday'],
            'workStartTime' => '09:00',
            'workEndTime' => '17:00',
            'dailyRateDivisor' => 30,
            'unpaidLeaveDailyRateMultiplier' => 1,
            'annualLeaveDays' => 21,
            'sickLeaveDays' => 10,
            'emergencyLeaveDays' => 6,
            'leaveWorkflow' => 'employee_request_admin_approval',
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
    }
}
