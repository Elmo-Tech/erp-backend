<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration adds performance indexes to frequently queried columns
     * to improve query performance as the database grows.
     */
    public function up(): void
    {
        // Tasks table indexes
        Schema::table('tasks', function (Blueprint $table) {
            // Composite index for project tasks filtered by status
            $table->index(['project_id', 'status'], 'idx_tasks_project_status');
            
            // Composite index for user's assigned tasks filtered by status
            $table->index(['assigned_to', 'status'], 'idx_tasks_assigned_status');
            
            // Index for hierarchical queries (parent-child relationships)
            $table->index(['parent_id', 'status'], 'idx_tasks_parent_status');
            
            // Index for due date queries
            $table->index('due_date', 'idx_tasks_due_date');
            
            // Index for task type filtering
            $table->index('type', 'idx_tasks_type');
        });

        // Time entries table indexes
        Schema::table('time_entries', function (Blueprint $table) {
            // Composite index for user's time entries
            $table->index(['user_id', 'start_time'], 'idx_time_entries_user_start');
            
            // Composite index for polymorphic relationship queries
            $table->index(['trackable_type', 'trackable_id'], 'idx_time_entries_trackable');
            
            // Index for date range queries
            $table->index('start_time', 'idx_time_entries_start_time');
            $table->index('end_time', 'idx_time_entries_end_time');
        });

        // Attendances table indexes
        Schema::table('attendances', function (Blueprint $table) {
            // Composite index for user's attendance by date
            $table->index(['user_id', 'date'], 'idx_attendances_user_date');
            
            // Index for date range queries
            $table->index('date', 'idx_attendances_date');
            
            // Index for clock in/out time queries
            $table->index('clock_in', 'idx_attendances_clock_in');
        });

        // Projects table indexes
        Schema::table('projects', function (Blueprint $table) {
            // Composite index for client's projects filtered by status
            $table->index(['client_id', 'status'], 'idx_projects_client_status');
            
            // Index for status filtering
            $table->index('status', 'idx_projects_status');
            
            // Index for deadline queries
            $table->index('deadline', 'idx_projects_deadline');
            
            // Index for quotation relationship
            $table->index('quotation_id', 'idx_projects_quotation');
        });

        // Invoices table indexes
        Schema::table('invoices', function (Blueprint $table) {
            // Note: invoices table has project_id, not client_id
            // Composite index for project's invoices filtered by status
            $table->index(['project_id', 'status'], 'idx_invoices_project_status');
            
            // Index for due date queries (overdue invoices)
            $table->index('due_date', 'idx_invoices_due_date');
            
            // Index for status filtering
            $table->index('status', 'idx_invoices_status');
            
            // Note: issue_date column doesn't exist in invoices table
        });

        // Leads table indexes
        Schema::table('leads', function (Blueprint $table) {
            // Index for status filtering
            $table->index('status', 'idx_leads_status');
            
            // Index for priority filtering
            $table->index('priority', 'idx_leads_priority');
            
            // Note: leads table has 'lead_temperature', not 'temperature'
            // Index for temperature filtering
            $table->index('lead_temperature', 'idx_leads_lead_temperature');
            
            // Index for created_by (sales rep)
            $table->index('created_by', 'idx_leads_created_by');
            
            // Index for tech_lead_id
            $table->index('tech_lead_id', 'idx_leads_tech_lead');
            
            // Note: leads table has 'next_follow_up_at', not 'follow_up_date'
            // Index for follow_up_date
            $table->index('next_follow_up_at', 'idx_leads_next_follow_up_at');
        });

        // Quotations table indexes
        Schema::table('quotations', function (Blueprint $table) {
            // Index for lead relationship
            $table->index('lead_id', 'idx_quotations_lead');
            
            // Index for status filtering
            $table->index('status', 'idx_quotations_status');
            
            // Note: expiry_date column doesn't exist in base quotations table
            
            // Index for created_by
            $table->index('created_by', 'idx_quotations_created_by');
        });

        // Contracts table indexes
        Schema::table('contracts', function (Blueprint $table) {
            // Index for quotation relationship
            $table->index('quotation_id', 'idx_contracts_quotation');
            
            // Note: contracts table has quotation_id and project_id, not client_id
            // Index for project relationship
            $table->index('project_id', 'idx_contracts_project');
            
            // Index for status filtering
            $table->index('status', 'idx_contracts_status');
            
            // Index for start/end date queries
            $table->index('start_date', 'idx_contracts_start_date');
            $table->index('end_date', 'idx_contracts_end_date');
        });

        // Project members table indexes
        Schema::table('project_members', function (Blueprint $table) {
            // Composite index for user's projects
            $table->index(['user_id', 'project_id'], 'idx_project_members_user_project');
            
            // Index for role filtering
            $table->index('role', 'idx_project_members_role');
        });

        // Task assignees table indexes
        Schema::table('task_assignees', function (Blueprint $table) {
            // Composite index for user's assigned tasks
            $table->index(['user_id', 'task_id'], 'idx_task_assignees_user_task');
        });

        // Audit logs table indexes
        Schema::table('audit_logs', function (Blueprint $table) {
            // Index for user filtering
            $table->index('user_id', 'idx_audit_logs_user');
            
            // Index for event type filtering
            $table->index('event_type', 'idx_audit_logs_event_type');
            
            // Index for severity filtering
            $table->index('severity', 'idx_audit_logs_severity');
            
            // Composite index for polymorphic relationship
            $table->index(['auditable_type', 'auditable_id'], 'idx_audit_logs_auditable');
            
            // Index for date range queries
            $table->index('created_at', 'idx_audit_logs_created_at');
        });

        // Point transactions table indexes
        Schema::table('point_transactions', function (Blueprint $table) {
            // Index for user filtering
            $table->index('user_id', 'idx_point_transactions_user');
            
            // Index for type filtering
            $table->index('type', 'idx_point_transactions_type');
            
            // Note: point_transactions table has 'source_type/source_id', not 'reference_type/reference_id'
            // Composite index for polymorphic relationship
            $table->index(['source_type', 'source_id'], 'idx_point_transactions_source');
            
            // Index for date queries
            $table->index('created_at', 'idx_point_transactions_created_at');
        });

        // Performance evaluations table indexes
        Schema::table('performance_evaluations', function (Blueprint $table) {
            // Index for user filtering
            $table->index('user_id', 'idx_performance_evaluations_user');
            
            // Index for period filtering
            $table->index('evaluation_period_id', 'idx_performance_evaluations_period');
            
            // Index for status filtering
            $table->index('status', 'idx_performance_evaluations_status');
        });

        // Activities table indexes
        Schema::table('activities', function (Blueprint $table) {
            // Index for type filtering
            $table->index('type', 'idx_activities_type');
            
            // Index for status filtering
            $table->index('status', 'idx_activities_status');
            
            // Index for assigned_to
            $table->index('assigned_to', 'idx_activities_assigned_to');
            
            // Note: activities table has subject_type/subject_id, not related_to_type/related_to_id
            // Composite index for polymorphic relationship
            $table->index(['subject_type', 'subject_id'], 'idx_activities_subject');
            
            // Note: activities table has scheduled_at, not due_date
            // Index for scheduled date queries
            $table->index('scheduled_at', 'idx_activities_scheduled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Tasks table
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('idx_tasks_project_status');
            $table->dropIndex('idx_tasks_assigned_status');
            $table->dropIndex('idx_tasks_parent_status');
            $table->dropIndex('idx_tasks_due_date');
            $table->dropIndex('idx_tasks_type');
        });

        // Time entries table
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropIndex('idx_time_entries_user_start');
            $table->dropIndex('idx_time_entries_trackable');
            $table->dropIndex('idx_time_entries_start_time');
            $table->dropIndex('idx_time_entries_end_time');
        });

        // Attendances table
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('idx_attendances_user_date');
            $table->dropIndex('idx_attendances_date');
            $table->dropIndex('idx_attendances_clock_in');
        });

        // Projects table
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex('idx_projects_client_status');
            $table->dropIndex('idx_projects_status');
            $table->dropIndex('idx_projects_deadline');
            $table->dropIndex('idx_projects_quotation');
        });

        // Invoices table
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('idx_invoices_project_status');
            $table->dropIndex('idx_invoices_due_date');
            $table->dropIndex('idx_invoices_status');
        });

        // Leads table
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('idx_leads_status');
            $table->dropIndex('idx_leads_priority');
            $table->dropIndex('idx_leads_lead_temperature');
            $table->dropIndex('idx_leads_created_by');
            $table->dropIndex('idx_leads_tech_lead');
            $table->dropIndex('idx_leads_next_follow_up_at');
        });

        // Quotations table
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropIndex('idx_quotations_lead');
            $table->dropIndex('idx_quotations_status');
            $table->dropIndex('idx_quotations_created_by');
        });

        // Contracts table
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropIndex('idx_contracts_quotation');
            $table->dropIndex('idx_contracts_project');
            $table->dropIndex('idx_contracts_status');
            $table->dropIndex('idx_contracts_start_date');
            $table->dropIndex('idx_contracts_end_date');
        });

        // Project members table
        Schema::table('project_members', function (Blueprint $table) {
            $table->dropIndex('idx_project_members_user_project');
            $table->dropIndex('idx_project_members_role');
        });

        // Task assignees table
        Schema::table('task_assignees', function (Blueprint $table) {
            $table->dropIndex('idx_task_assignees_user_task');
        });

        // Audit logs table
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_audit_logs_user');
            $table->dropIndex('idx_audit_logs_event_type');
            $table->dropIndex('idx_audit_logs_severity');
            $table->dropIndex('idx_audit_logs_auditable');
            $table->dropIndex('idx_audit_logs_created_at');
        });

        // Point transactions table
        Schema::table('point_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_point_transactions_user');
            $table->dropIndex('idx_point_transactions_type');
            $table->dropIndex('idx_point_transactions_source');
            $table->dropIndex('idx_point_transactions_created_at');
        });

        // Performance evaluations table
        Schema::table('performance_evaluations', function (Blueprint $table) {
            $table->dropIndex('idx_performance_evaluations_user');
            $table->dropIndex('idx_performance_evaluations_period');
            $table->dropIndex('idx_performance_evaluations_status');
        });

        // Activities table
        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex('idx_activities_type');
            $table->dropIndex('idx_activities_status');
            $table->dropIndex('idx_activities_assigned_to');
            $table->dropIndex('idx_activities_subject');
            $table->dropIndex('idx_activities_scheduled_at');
        });
    }
};
