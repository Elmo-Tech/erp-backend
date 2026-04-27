<?php

namespace Tests\Unit\Policies;

use App\Enums\InvoiceStatus;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Project\Models\Project;
use App\Models\User;
use App\Policies\InvoicePolicy;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Property-Based Tests for InvoicePolicy Authorization
 *
 * Feature: client-portal-security, Property 8: InvoicePolicy Authorization
 * Validates: Requirements 12.2, 12.3, 12.4, 12.5
 */
class InvoicePolicyPropertyTest extends TestCase
{
    use RefreshDatabase, TestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles if they don't exist
        Role::firstOrCreate(['name' => 'client']);
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'super-admin']);
        Role::firstOrCreate(['name' => 'employee']);
    }

    /**
     * Property 8: InvoicePolicy Authorization - Client View Access
     *
     * For any client user and for any invoice, view authorization SHALL succeed
     * only if the invoice belongs to a project where client_id equals user.id.
     *
     * **Validates: Requirements 12.2**
     */
    public function test_client_can_only_view_own_invoices(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(2, 5) // Number of clients
            )
            ->then(function ($numClients) {
                $policy = new InvoicePolicy;

                // Create multiple clients
                $clients = User::factory()->count($numClients)->create();
                $clientProfiles = [];
                foreach ($clients as $client) {
                    $client->assignRole('client');
                    $clientProfiles[$client->id] = $this->ensureClientProfile($client);
                }

                // Create projects and invoices for each client
                $invoices = [];
                foreach ($clients as $client) {
                    $project = Project::factory()->create(['client_id' => $clientProfiles[$client->id]->id]);
                    $invoices[] = Invoice::factory()->create(['project_id' => $project->id]);
                }

                // Test that each client can only view invoices for their own projects
                foreach ($clients as $clientIndex => $client) {
                    foreach ($invoices as $invoiceIndex => $invoice) {
                        $canView = $policy->view($client, $invoice);

                        if ($clientIndex === $invoiceIndex) {
                            // Client should be able to view invoices for their own projects
                            $this->assertTrue($canView,
                                "Client {$client->id} should be able to view invoice {$invoice->id} for their own project {$invoice->project_id}");
                        } else {
                            // Client should NOT be able to view invoices for other clients' projects
                            $this->assertFalse($canView,
                                "Client {$client->id} should NOT be able to view invoice {$invoice->id} for project {$invoice->project_id} owned by client {$invoice->project->client_id}");
                        }
                    }
                }
            });
    }

    /**
     * Property 8: InvoicePolicy Authorization - Admin/Employee View Access
     *
     * For any admin or employee user, view authorization SHALL succeed
     * for all invoices.
     *
     * **Validates: Requirements 12.3**
     */
    public function test_admin_and_employee_can_view_all_invoices(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(1, 5), // Number of invoices
                Generator\elements(['admin', 'super-admin', 'employee'])
            )
            ->then(function ($numInvoices, $role) {
                $policy = new InvoicePolicy;

                // Create admin/employee user
                $user = User::factory()->create();
                $user->assignRole($role);

                // Create multiple invoices with different clients
                $clients = User::factory()->count($numInvoices)->create();
                $clientProfiles = [];
                foreach ($clients as $client) {
                    $client->assignRole('client');
                    $clientProfiles[$client->id] = $this->ensureClientProfile($client);
                }

                $invoices = [];
                foreach ($clients as $client) {
                    $project = Project::factory()->create(['client_id' => $clientProfiles[$client->id]->id]);
                    $invoices[] = Invoice::factory()->create(['project_id' => $project->id]);
                }

                // Admin/employee should be able to view all invoices
                foreach ($invoices as $invoice) {
                    $canView = $policy->view($user, $invoice);
                    $this->assertTrue($canView,
                        "User with role '{$role}' should be able to view invoice {$invoice->id} for project {$invoice->project_id} owned by client {$invoice->project->client_id}");
                }
            });
    }

    /**
     * Property 8: InvoicePolicy Authorization - Client Create/Update/Delete Restrictions
     *
     * For any client user, create, update, and delete authorization SHALL fail.
     *
     * **Validates: Requirements 12.4**
     */
    public function test_client_cannot_create_update_delete_invoices(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(1, 3) // Number of clients
            )
            ->then(function ($numClients) {
                $policy = new InvoicePolicy;

                // Create client users
                $clients = User::factory()->count($numClients)->create();
                $clientProfiles = [];
                foreach ($clients as $client) {
                    $client->assignRole('client');
                    $clientProfiles[$client->id] = $this->ensureClientProfile($client);
                }

                // Create a project and invoice for testing update/delete
                $project = Project::factory()->create(['client_id' => $clientProfiles[$clients[0]->id]->id]);
                $invoice = Invoice::factory()->create(['project_id' => $project->id]);

                foreach ($clients as $client) {
                    // Test create permission
                    $canCreate = $policy->create($client);
                    $this->assertFalse($canCreate,
                        "Client {$client->id} should NOT be able to create invoices");

                    // Test update permission
                    $canUpdate = $policy->update($client, $invoice);
                    $this->assertFalse($canUpdate,
                        "Client {$client->id} should NOT be able to update invoice {$invoice->id}");

                    // Test delete permission
                    $canDelete = $policy->delete($client, $invoice);
                    $this->assertFalse($canDelete,
                        "Client {$client->id} should NOT be able to delete invoice {$invoice->id}");
                }
            });
    }

    /**
     * Property 8: InvoicePolicy Authorization - Admin Create/Update/Delete Access
     *
     * For any admin user, create, update, and delete authorization SHALL succeed.
     *
     * **Validates: Requirements 12.5**
     */
    public function test_admin_can_create_update_delete_invoices(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\elements(['admin', 'super-admin', 'employee'])
            )
            ->then(function ($role) {
                $policy = new InvoicePolicy;

                // Create admin/employee user
                $user = User::factory()->create();
                $user->assignRole($role);

                // Create a client, project, and invoice for testing
                $client = User::factory()->create();
                $client->assignRole('client');
                $clientProfile = $this->ensureClientProfile($client);
                $project = Project::factory()->create(['client_id' => $clientProfile->id]);
                $invoice = Invoice::factory()->create(['project_id' => $project->id]);

                // Test create permission
                $canCreate = $policy->create($user);
                $this->assertTrue($canCreate,
                    "User with role '{$role}' should be able to create invoices");

                // Test update permission
                $canUpdate = $policy->update($user, $invoice);
                $this->assertTrue($canUpdate,
                    "User with role '{$role}' should be able to update invoice {$invoice->id}");

                // Test delete permission
                $canDelete = $policy->delete($user, $invoice);
                $this->assertTrue($canDelete,
                    "User with role '{$role}' should be able to delete invoice {$invoice->id}");
            });
    }

    /**
     * Property 8: InvoicePolicy Authorization - Client Pay Access
     *
     * For any client user, pay authorization SHALL succeed only for their own
     * payable invoices.
     *
     * **Validates: Requirements 12.2, 12.3**
     */
    public function test_client_can_only_pay_own_payable_invoices(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(2, 4), // Number of clients
                Generator\elements([InvoiceStatus::SENT, InvoiceStatus::OVERDUE, InvoiceStatus::DRAFT, InvoiceStatus::PAID, InvoiceStatus::CANCELLED])
            )
            ->then(function ($numClients, $invoiceStatus) {
                $policy = new InvoicePolicy;

                // Create multiple clients
                $clients = User::factory()->count($numClients)->create();
                $clientProfiles = [];
                foreach ($clients as $client) {
                    $client->assignRole('client');
                    $clientProfiles[$client->id] = $this->ensureClientProfile($client);
                }

                // Create projects and invoices for each client
                $invoices = [];
                foreach ($clients as $client) {
                    $project = Project::factory()->create(['client_id' => $clientProfiles[$client->id]->id]);
                    $invoices[] = Invoice::factory()->create([
                        'project_id' => $project->id,
                        'status' => $invoiceStatus,
                    ]);
                }

                // Test that each client can only pay their own payable invoices
                foreach ($clients as $clientIndex => $client) {
                    foreach ($invoices as $invoiceIndex => $invoice) {
                        $canPay = $policy->pay($client, $invoice);

                        if ($clientIndex === $invoiceIndex) {
                            // Client should be able to pay their own invoices only if payable
                            if ($invoice->isPayable()) {
                                $this->assertTrue($canPay,
                                    "Client {$client->id} should be able to pay their own payable invoice {$invoice->id} with status {$invoiceStatus->value}");
                            } else {
                                $this->assertFalse($canPay,
                                    "Client {$client->id} should NOT be able to pay their own non-payable invoice {$invoice->id} with status {$invoiceStatus->value}");
                            }
                        } else {
                            // Client should NOT be able to pay other clients' invoices regardless of status
                            $this->assertFalse($canPay,
                                "Client {$client->id} should NOT be able to pay invoice {$invoice->id} owned by client {$invoice->project->client_id}");
                        }
                    }
                }
            });
    }

    /**
     * Property 8: InvoicePolicy Authorization - Admin Pay Access
     *
     * For any admin user, pay authorization SHALL succeed for any payable invoice.
     *
     * **Validates: Requirements 12.5**
     */
    public function test_admin_can_pay_any_payable_invoice(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\elements(['admin', 'super-admin']),
                Generator\elements([InvoiceStatus::SENT, InvoiceStatus::OVERDUE, InvoiceStatus::DRAFT, InvoiceStatus::PAID, InvoiceStatus::CANCELLED])
            )
            ->then(function ($role, $invoiceStatus) {
                $policy = new InvoicePolicy;

                // Create admin user
                $admin = User::factory()->create();
                $admin->assignRole($role);

                // Create a client, project, and invoice
                $client = User::factory()->create();
                $client->assignRole('client');
                $clientProfile = $this->ensureClientProfile($client);
                $project = Project::factory()->create(['client_id' => $clientProfile->id]);
                $invoice = Invoice::factory()->create([
                    'project_id' => $project->id,
                    'status' => $invoiceStatus,
                ]);

                $canPay = $policy->pay($admin, $invoice);

                if ($invoice->isPayable()) {
                    $this->assertTrue($canPay,
                        "Admin with role '{$role}' should be able to pay any payable invoice {$invoice->id} with status {$invoiceStatus->value}");
                } else {
                    $this->assertFalse($canPay,
                        "Admin with role '{$role}' should NOT be able to pay non-payable invoice {$invoice->id} with status {$invoiceStatus->value}");
                }
            });
    }

    /**
     * Property 8: InvoicePolicy Authorization - Employee Pay Restrictions
     *
     * For any employee user, pay authorization SHALL fail (employees cannot pay invoices).
     *
     * **Validates: Requirements 12.5**
     */
    public function test_employee_cannot_pay_invoices(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\elements([InvoiceStatus::SENT, InvoiceStatus::OVERDUE])
            )
            ->then(function ($invoiceStatus) {
                $policy = new InvoicePolicy;

                // Create employee user
                $employee = User::factory()->create();
                $employee->assignRole('employee');

                // Create a client, project, and payable invoice
                $client = User::factory()->create();
                $client->assignRole('client');
                $clientProfile = $this->ensureClientProfile($client);
                $project = Project::factory()->create(['client_id' => $clientProfile->id]);
                $invoice = Invoice::factory()->create([
                    'project_id' => $project->id,
                    'status' => $invoiceStatus,
                ]);

                $canPay = $policy->pay($employee, $invoice);

                // Employee should NOT be able to pay invoices (not in the allowed roles)
                $this->assertFalse($canPay,
                    "Employee should NOT be able to pay invoice {$invoice->id} with status {$invoiceStatus->value}");
            });
    }
}
