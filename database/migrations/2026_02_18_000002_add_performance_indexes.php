<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migration: Add Performance Indexes
 * 
 * This migration adds missing indexes to improve query performance
 * on frequently queried columns.
 * 
 * Performance: These indexes target common query patterns identified
 * during code review and will significantly improve dashboard and
 * listing page load times.
 */
return new class extends Migration
{
    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $schemaManager = $connection->getSchemaBuilder();
        
        // Get all indexes for the table
        $indexes = $schemaManager->getIndexes($table);
        
        // Check if the index name exists
        foreach ($indexes as $index) {
            if ($index['name'] === $indexName) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Users table indexes
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!$this->indexExists('users', 'users_name_index')) {
                    $table->index('name', 'users_name_index');
                }
                if (!$this->indexExists('users', 'users_is_active_index')) {
                    $table->index('is_active', 'users_is_active_index');
                }
                if (!$this->indexExists('users', 'users_is_employee_index')) {
                    $table->index('is_employee', 'users_is_employee_index');
                }
            });
        }

        // Tasks table indexes
        if (Schema::hasTable('tasks')) {
            Schema::table('tasks', function (Blueprint $table) {
                if (!$this->indexExists('tasks', 'tasks_created_at_index')) {
                    $table->index('created_at', 'tasks_created_at_index');
                }
                if (!$this->indexExists('tasks', 'tasks_status_index')) {
                    $table->index('status', 'tasks_status_index');
                }
                if (!$this->indexExists('tasks', 'tasks_priority_index')) {
                    $table->index('priority', 'tasks_priority_index');
                }
                if (!$this->indexExists('tasks', 'tasks_project_id_index')) {
                    $table->index('project_id', 'tasks_project_id_index');
                }
                if (!$this->indexExists('tasks', 'tasks_parent_id_index')) {
                    $table->index('parent_id', 'tasks_parent_id_index');
                }
                if (!$this->indexExists('tasks', 'tasks_due_date_index')) {
                    $table->index('due_date', 'tasks_due_date_index');
                }
            });
        }

        // Projects table indexes
        if (Schema::hasTable('projects')) {
            Schema::table('projects', function (Blueprint $table) {
                if (!$this->indexExists('projects', 'projects_created_at_index')) {
                    $table->index('created_at', 'projects_created_at_index');
                }
                if (!$this->indexExists('projects', 'projects_status_index')) {
                    $table->index('status', 'projects_status_index');
                }
                if (!$this->indexExists('projects', 'projects_deadline_index')) {
                    $table->index('deadline', 'projects_deadline_index');
                }
                if (!$this->indexExists('projects', 'projects_client_id_index')) {
                    $table->index('client_id', 'projects_client_id_index');
                }
            });
        }

        // Leads table indexes
        if (Schema::hasTable('leads')) {
            Schema::table('leads', function (Blueprint $table) {
                if (!$this->indexExists('leads', 'leads_created_at_index')) {
                    $table->index('created_at', 'leads_created_at_index');
                }
                if (!$this->indexExists('leads', 'leads_status_index')) {
                    $table->index('status', 'leads_status_index');
                }
                if (!$this->indexExists('leads', 'leads_priority_index')) {
                    $table->index('priority', 'leads_priority_index');
                }
                if (!$this->indexExists('leads', 'leads_assigned_to_index')) {
                    $table->index('assigned_to', 'leads_assigned_to_index');
                }
                if (!$this->indexExists('leads', 'leads_client_id_index')) {
                    $table->index('client_id', 'leads_client_id_index');
                }
            });
        }

        // Clients table indexes
        if (Schema::hasTable('clients')) {
            Schema::table('clients', function (Blueprint $table) {
                if (!$this->indexExists('clients', 'clients_created_at_index')) {
                    $table->index('created_at', 'clients_created_at_index');
                }
                if (!$this->indexExists('clients', 'clients_status_index')) {
                    $table->index('status', 'clients_status_index');
                }
                if (!$this->indexExists('clients', 'clients_industry_index')) {
                    $table->index('industry', 'clients_industry_index');
                }
            });
        }

        // Invoices table indexes
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (!$this->indexExists('invoices', 'invoices_created_at_index')) {
                    $table->index('created_at', 'invoices_created_at_index');
                }
                if (!$this->indexExists('invoices', 'invoices_status_index')) {
                    $table->index('status', 'invoices_status_index');
                }
                if (!$this->indexExists('invoices', 'invoices_client_id_index')) {
                    $table->index('client_id', 'invoices_client_id_index');
                }
                if (!$this->indexExists('invoices', 'invoices_project_id_index')) {
                    $table->index('project_id', 'invoices_project_id_index');
                }
                if (!$this->indexExists('invoices', 'invoices_due_date_index')) {
                    $table->index('due_date', 'invoices_due_date_index');
                }
            });
        }

        // Quotations table indexes
        if (Schema::hasTable('quotations')) {
            Schema::table('quotations', function (Blueprint $table) {
                if (!$this->indexExists('quotations', 'quotations_created_at_index')) {
                    $table->index('created_at', 'quotations_created_at_index');
                }
                if (!$this->indexExists('quotations', 'quotations_status_index')) {
                    $table->index('status', 'quotations_status_index');
                }
                if (!$this->indexExists('quotations', 'quotations_client_id_index')) {
                    $table->index('client_id', 'quotations_client_id_index');
                }
                if (!$this->indexExists('quotations', 'quotations_valid_until_index')) {
                    $table->index('valid_until', 'quotations_valid_until_index');
                }
            });
        }

        // Contracts table indexes
        if (Schema::hasTable('contracts')) {
            Schema::table('contracts', function (Blueprint $table) {
                if (!$this->indexExists('contracts', 'contracts_created_at_index')) {
                    $table->index('created_at', 'contracts_created_at_index');
                }
                if (!$this->indexExists('contracts', 'contracts_status_index')) {
                    $table->index('status', 'contracts_status_index');
                }
                if (!$this->indexExists('contracts', 'contracts_client_id_index')) {
                    $table->index('client_id', 'contracts_client_id_index');
                }
                if (!$this->indexExists('contracts', 'contracts_start_date_index')) {
                    $table->index('start_date', 'contracts_start_date_index');
                }
                if (!$this->indexExists('contracts', 'contracts_end_date_index')) {
                    $table->index('end_date', 'contracts_end_date_index');
                }
            });
        }

        // Proposals table indexes
        if (Schema::hasTable('proposals')) {
            Schema::table('proposals', function (Blueprint $table) {
                if (!$this->indexExists('proposals', 'proposals_created_at_index')) {
                    $table->index('created_at', 'proposals_created_at_index');
                }
                if (!$this->indexExists('proposals', 'proposals_status_index')) {
                    $table->index('status', 'proposals_status_index');
                }
                if (!$this->indexExists('proposals', 'proposals_client_id_index')) {
                    $table->index('client_id', 'proposals_client_id_index');
                }
            });
        }

        // Activities table indexes
        if (Schema::hasTable('activities')) {
            Schema::table('activities', function (Blueprint $table) {
                if (!$this->indexExists('activities', 'activities_created_at_index')) {
                    $table->index('created_at', 'activities_created_at_index');
                }
                if (!$this->indexExists('activities', 'activities_status_index')) {
                    $table->index('status', 'activities_status_index');
                }
                if (!$this->indexExists('activities', 'activities_type_index')) {
                    $table->index('type', 'activities_type_index');
                }
                if (!$this->indexExists('activities', 'activities_user_id_index')) {
                    $table->index('user_id', 'activities_user_id_index');
                }
            });
        }

        // Time entries table indexes
        if (Schema::hasTable('time_entries')) {
            Schema::table('time_entries', function (Blueprint $table) {
                if (!$this->indexExists('time_entries', 'time_entries_user_id_index')) {
                    $table->index('user_id', 'time_entries_user_id_index');
                }
                if (!$this->indexExists('time_entries', 'time_entries_project_id_index')) {
                    $table->index('project_id', 'time_entries_project_id_index');
                }
                if (!$this->indexExists('time_entries', 'time_entries_task_id_index')) {
                    $table->index('task_id', 'time_entries_task_id_index');
                }
                if (!$this->indexExists('time_entries', 'time_entries_date_index')) {
                    $table->index('date', 'time_entries_date_index');
                }
            });
        }

        // Milestones table indexes
        if (Schema::hasTable('milestones')) {
            Schema::table('milestones', function (Blueprint $table) {
                if (!$this->indexExists('milestones', 'milestones_project_id_index')) {
                    $table->index('project_id', 'milestones_project_id_index');
                }
                if (!$this->indexExists('milestones', 'milestones_status_index')) {
                    $table->index('status', 'milestones_status_index');
                }
                if (!$this->indexExists('milestones', 'milestones_due_date_index')) {
                    $table->index('due_date', 'milestones_due_date_index');
                }
            });
        }

        // Task deliverables table indexes
        if (Schema::hasTable('task_deliverables')) {
            Schema::table('task_deliverables', function (Blueprint $table) {
                if (!$this->indexExists('task_deliverables', 'task_deliverables_task_id_index')) {
                    $table->index('task_id', 'task_deliverables_task_id_index');
                }
                if (!$this->indexExists('task_deliverables', 'task_deliverables_status_index')) {
                    $table->index('status', 'task_deliverables_status_index');
                }
                if (!$this->indexExists('task_deliverables', 'task_deliverables_submitted_by_index')) {
                    $table->index('submitted_by', 'task_deliverables_submitted_by_index');
                }
            });
        }

        // Attendance table indexes
        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', function (Blueprint $table) {
                if (!$this->indexExists('attendances', 'attendances_user_id_index')) {
                    $table->index('user_id', 'attendances_user_id_index');
                }
                if (!$this->indexExists('attendances', 'attendances_date_index')) {
                    $table->index('date', 'attendances_date_index');
                }
            });
        }

        // Audit logs table indexes
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                if (!$this->indexExists('audit_logs', 'audit_logs_created_at_index')) {
                    $table->index('created_at', 'audit_logs_created_at_index');
                }
                if (!$this->indexExists('audit_logs', 'audit_logs_user_id_index')) {
                    $table->index('user_id', 'audit_logs_user_id_index');
                }
                if (!$this->indexExists('audit_logs', 'audit_logs_event_type_index')) {
                    $table->index('event_type', 'audit_logs_event_type_index');
                }
            });
        }

        // Point transactions table indexes
        if (Schema::hasTable('point_transactions')) {
            Schema::table('point_transactions', function (Blueprint $table) {
                if (!$this->indexExists('point_transactions', 'point_transactions_user_id_index')) {
                    $table->index('user_id', 'point_transactions_user_id_index');
                }
                if (!$this->indexExists('point_transactions', 'point_transactions_type_index')) {
                    $table->index('type', 'point_transactions_type_index');
                }
                if (!$this->indexExists('point_transactions', 'point_transactions_created_at_index')) {
                    $table->index('created_at', 'point_transactions_created_at_index');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Users
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if ($this->indexExists('users', 'users_name_index')) {
                    $table->dropIndex('users_name_index');
                }
                if ($this->indexExists('users', 'users_is_active_index')) {
                    $table->dropIndex('users_is_active_index');
                }
                if ($this->indexExists('users', 'users_is_employee_index')) {
                    $table->dropIndex('users_is_employee_index');
                }
            });
        }

        // Tasks
        if (Schema::hasTable('tasks')) {
            Schema::table('tasks', function (Blueprint $table) {
                if ($this->indexExists('tasks', 'tasks_created_at_index')) {
                    $table->dropIndex('tasks_created_at_index');
                }
                if ($this->indexExists('tasks', 'tasks_status_index')) {
                    $table->dropIndex('tasks_status_index');
                }
                if ($this->indexExists('tasks', 'tasks_priority_index')) {
                    $table->dropIndex('tasks_priority_index');
                }
                if ($this->indexExists('tasks', 'tasks_project_id_index')) {
                    $table->dropIndex('tasks_project_id_index');
                }
                if ($this->indexExists('tasks', 'tasks_parent_id_index')) {
                    $table->dropIndex('tasks_parent_id_index');
                }
                if ($this->indexExists('tasks', 'tasks_due_date_index')) {
                    $table->dropIndex('tasks_due_date_index');
                }
            });
        }

        // Projects
        if (Schema::hasTable('projects')) {
            Schema::table('projects', function (Blueprint $table) {
                if ($this->indexExists('projects', 'projects_created_at_index')) {
                    $table->dropIndex('projects_created_at_index');
                }
                if ($this->indexExists('projects', 'projects_status_index')) {
                    $table->dropIndex('projects_status_index');
                }
                if ($this->indexExists('projects', 'projects_deadline_index')) {
                    $table->dropIndex('projects_deadline_index');
                }
                if ($this->indexExists('projects', 'projects_client_id_index')) {
                    $table->dropIndex('projects_client_id_index');
                }
            });
        }

        // Leads
        if (Schema::hasTable('leads')) {
            Schema::table('leads', function (Blueprint $table) {
                if ($this->indexExists('leads', 'leads_created_at_index')) {
                    $table->dropIndex('leads_created_at_index');
                }
                if ($this->indexExists('leads', 'leads_status_index')) {
                    $table->dropIndex('leads_status_index');
                }
                if ($this->indexExists('leads', 'leads_priority_index')) {
                    $table->dropIndex('leads_priority_index');
                }
                if ($this->indexExists('leads', 'leads_assigned_to_index')) {
                    $table->dropIndex('leads_assigned_to_index');
                }
                if ($this->indexExists('leads', 'leads_client_id_index')) {
                    $table->dropIndex('leads_client_id_index');
                }
            });
        }

        // Clients
        if (Schema::hasTable('clients')) {
            Schema::table('clients', function (Blueprint $table) {
                if ($this->indexExists('clients', 'clients_created_at_index')) {
                    $table->dropIndex('clients_created_at_index');
                }
                if ($this->indexExists('clients', 'clients_status_index')) {
                    $table->dropIndex('clients_status_index');
                }
                if ($this->indexExists('clients', 'clients_industry_index')) {
                    $table->dropIndex('clients_industry_index');
                }
            });
        }

        // Invoices
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if ($this->indexExists('invoices', 'invoices_created_at_index')) {
                    $table->dropIndex('invoices_created_at_index');
                }
                if ($this->indexExists('invoices', 'invoices_status_index')) {
                    $table->dropIndex('invoices_status_index');
                }
                if ($this->indexExists('invoices', 'invoices_client_id_index')) {
                    $table->dropIndex('invoices_client_id_index');
                }
                if ($this->indexExists('invoices', 'invoices_project_id_index')) {
                    $table->dropIndex('invoices_project_id_index');
                }
                if ($this->indexExists('invoices', 'invoices_due_date_index')) {
                    $table->dropIndex('invoices_due_date_index');
                }
            });
        }

        // Quotations
        if (Schema::hasTable('quotations')) {
            Schema::table('quotations', function (Blueprint $table) {
                if ($this->indexExists('quotations', 'quotations_created_at_index')) {
                    $table->dropIndex('quotations_created_at_index');
                }
                if ($this->indexExists('quotations', 'quotations_status_index')) {
                    $table->dropIndex('quotations_status_index');
                }
                if ($this->indexExists('quotations', 'quotations_client_id_index')) {
                    $table->dropIndex('quotations_client_id_index');
                }
                if ($this->indexExists('quotations', 'quotations_valid_until_index')) {
                    $table->dropIndex('quotations_valid_until_index');
                }
            });
        }

        // Contracts
        if (Schema::hasTable('contracts')) {
            Schema::table('contracts', function (Blueprint $table) {
                if ($this->indexExists('contracts', 'contracts_created_at_index')) {
                    $table->dropIndex('contracts_created_at_index');
                }
                if ($this->indexExists('contracts', 'contracts_status_index')) {
                    $table->dropIndex('contracts_status_index');
                }
                if ($this->indexExists('contracts', 'contracts_client_id_index')) {
                    $table->dropIndex('contracts_client_id_index');
                }
                if ($this->indexExists('contracts', 'contracts_start_date_index')) {
                    $table->dropIndex('contracts_start_date_index');
                }
                if ($this->indexExists('contracts', 'contracts_end_date_index')) {
                    $table->dropIndex('contracts_end_date_index');
                }
            });
        }

        // Proposals
        if (Schema::hasTable('proposals')) {
            Schema::table('proposals', function (Blueprint $table) {
                if ($this->indexExists('proposals', 'proposals_created_at_index')) {
                    $table->dropIndex('proposals_created_at_index');
                }
                if ($this->indexExists('proposals', 'proposals_status_index')) {
                    $table->dropIndex('proposals_status_index');
                }
                if ($this->indexExists('proposals', 'proposals_client_id_index')) {
                    $table->dropIndex('proposals_client_id_index');
                }
            });
        }

        // Activities
        if (Schema::hasTable('activities')) {
            Schema::table('activities', function (Blueprint $table) {
                if ($this->indexExists('activities', 'activities_created_at_index')) {
                    $table->dropIndex('activities_created_at_index');
                }
                if ($this->indexExists('activities', 'activities_status_index')) {
                    $table->dropIndex('activities_status_index');
                }
                if ($this->indexExists('activities', 'activities_type_index')) {
                    $table->dropIndex('activities_type_index');
                }
                if ($this->indexExists('activities', 'activities_user_id_index')) {
                    $table->dropIndex('activities_user_id_index');
                }
            });
        }

        // Time entries
        if (Schema::hasTable('time_entries')) {
            Schema::table('time_entries', function (Blueprint $table) {
                if ($this->indexExists('time_entries', 'time_entries_user_id_index')) {
                    $table->dropIndex('time_entries_user_id_index');
                }
                if ($this->indexExists('time_entries', 'time_entries_project_id_index')) {
                    $table->dropIndex('time_entries_project_id_index');
                }
                if ($this->indexExists('time_entries', 'time_entries_task_id_index')) {
                    $table->dropIndex('time_entries_task_id_index');
                }
                if ($this->indexExists('time_entries', 'time_entries_date_index')) {
                    $table->dropIndex('time_entries_date_index');
                }
            });
        }

        // Milestones
        if (Schema::hasTable('milestones')) {
            Schema::table('milestones', function (Blueprint $table) {
                if ($this->indexExists('milestones', 'milestones_project_id_index')) {
                    $table->dropIndex('milestones_project_id_index');
                }
                if ($this->indexExists('milestones', 'milestones_status_index')) {
                    $table->dropIndex('milestones_status_index');
                }
                if ($this->indexExists('milestones', 'milestones_due_date_index')) {
                    $table->dropIndex('milestones_due_date_index');
                }
            });
        }

        // Task deliverables
        if (Schema::hasTable('task_deliverables')) {
            Schema::table('task_deliverables', function (Blueprint $table) {
                if ($this->indexExists('task_deliverables', 'task_deliverables_task_id_index')) {
                    $table->dropIndex('task_deliverables_task_id_index');
                }
                if ($this->indexExists('task_deliverables', 'task_deliverables_status_index')) {
                    $table->dropIndex('task_deliverables_status_index');
                }
                if ($this->indexExists('task_deliverables', 'task_deliverables_submitted_by_index')) {
                    $table->dropIndex('task_deliverables_submitted_by_index');
                }
            });
        }

        // Attendance
        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', function (Blueprint $table) {
                if ($this->indexExists('attendances', 'attendances_user_id_index')) {
                    $table->dropIndex('attendances_user_id_index');
                }
                if ($this->indexExists('attendances', 'attendances_date_index')) {
                    $table->dropIndex('attendances_date_index');
                }
            });
        }

        // Audit logs
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                if ($this->indexExists('audit_logs', 'audit_logs_created_at_index')) {
                    $table->dropIndex('audit_logs_created_at_index');
                }
                if ($this->indexExists('audit_logs', 'audit_logs_user_id_index')) {
                    $table->dropIndex('audit_logs_user_id_index');
                }
                if ($this->indexExists('audit_logs', 'audit_logs_event_type_index')) {
                    $table->dropIndex('audit_logs_event_type_index');
                }
            });
        }

        // Point transactions
        if (Schema::hasTable('point_transactions')) {
            Schema::table('point_transactions', function (Blueprint $table) {
                if ($this->indexExists('point_transactions', 'point_transactions_user_id_index')) {
                    $table->dropIndex('point_transactions_user_id_index');
                }
                if ($this->indexExists('point_transactions', 'point_transactions_type_index')) {
                    $table->dropIndex('point_transactions_type_index');
                }
                if ($this->indexExists('point_transactions', 'point_transactions_created_at_index')) {
                    $table->dropIndex('point_transactions_created_at_index');
                }
            });
        }
    }
};
