<?php

return [
    // General
    'validation_failed' => 'Validation failed',
    'validation_error' => 'Validation error',
    'no_permission' => 'You do not have permission to perform this action',
    'unauthorized' => 'Unauthorized access',
    'unauthorized_send_invoice' => 'You do not have permission to send invoices',

    // Users
    'users_retrieved' => 'Users retrieved successfully',
    'user_retrieved' => 'User retrieved successfully',
    'user_created' => 'User created successfully',
    'user_updated' => 'User updated successfully',
    'user_deleted' => 'User deleted successfully',
    'user_not_found' => 'User not found',
    'employee_created' => 'Employee created successfully',
    'employee_updated' => 'Employee updated successfully',
    'employee_deleted' => 'Employee deleted successfully',
    'employee_not_found' => 'Employee not found',
    'employee_activated' => 'Employee activated successfully',
    'employee_deactivated' => 'Employee deactivated successfully',
    'user_activated' => 'User activated successfully',
    'user_deactivated' => 'User deactivated successfully',
    'cannot_delete_self' => 'You cannot delete your own account',
    'roles_assigned' => 'Roles assigned successfully',

    // Performance Evaluation
    'evaluation_period_created' => 'Evaluation period created successfully',
    'period_activated' => 'Evaluation period activated and evaluations created for all employees',
    'period_already_activated' => 'This period has already been activated',
    'evaluation_calculated' => 'Evaluation scores calculated successfully',
    'evaluation_updated' => 'Evaluation updated successfully',
    'evaluation_approved' => 'Evaluation approved successfully',

    // Code Reviews
    'code_review_created' => 'Code review created successfully',
    'code_review_updated' => 'Code review updated successfully',
    'code_review_approved' => 'Code review approved successfully',
    'code_review_rejected' => 'Code review rejected successfully',
    'revision_requested' => 'Revision requested successfully',

    // Innovation Logs
    'innovation_logged' => 'Innovation logged successfully',
    'innovation_verified' => 'Innovation verified successfully',
    'innovation_rejected' => 'Innovation rejected successfully',
    'innovation_deleted' => 'Innovation log deleted successfully',
    'cannot_delete_verified' => 'Cannot delete a verified innovation log',

    // Milestones & Invoices
    'milestone_retrieved' => 'Milestone retrieved successfully',
    'milestone_marked_paid' => 'Milestone marked as paid successfully',
    'invoice_created_from_milestone' => 'Invoice created from milestone successfully',
    'invoice_linked_to_milestone' => 'Invoice linked to milestone successfully',
    'milestone_already_has_invoice' => 'Milestone already has an invoice linked',

    // Invoices
    'invoices_retrieved' => 'Invoices retrieved successfully',
    'invoice_retrieved' => 'Invoice retrieved successfully',
    'invoice_created' => 'Invoice created successfully',
    'invoice_updated' => 'Invoice updated successfully',
    'invoice_deleted' => 'Invoice deleted successfully',
    'invoice_sent' => 'Invoice sent successfully',
    'invoice_marked_paid' => 'Invoice marked as paid successfully',
    'milestone_invoice_generated' => 'Milestone invoice generated successfully',
    'pending_invoices_generated' => 'Pending invoices generated successfully',
    'cannot_delete_non_draft_invoice' => 'Only draft invoices can be deleted',

    // Audit Logs
    'audit_logs_retrieved' => 'Audit logs retrieved successfully',
    'audit_log_retrieved' => 'Audit log retrieved successfully',
    'audit_stats_retrieved' => 'Audit statistics retrieved successfully',

    // Tasks
    'task_status_updated' => 'Task status updated successfully',
    'task_marked_complete' => 'Task marked as complete successfully',
    'no_permission_manage_team' => 'You do not have permission to manage team on this task type',
    'no_permission_manage_test_scenarios' => 'You do not have permission to manage test scenarios on this task type',
    'no_permission_verify_test_scenarios' => 'Only Admin and Task Reviewers can verify test scenarios',
    'cannot_create_invoice_for_paid_milestone' => 'Cannot create invoice for paid milestone',

    // Status Updates
    'task_status_updated' => 'Task status updated successfully',
    'project_status_updated' => 'Project status updated successfully',
    'status_already_set' => 'Status is already set to :status',
    'no_permission_manage_team' => 'You do not have permission to manage team on this task type',
    'no_permission_manage_test_scenarios' => 'You do not have permission to manage test scenarios on this task type',
    'contract_must_have_project' => 'Contract must be linked to a project to create invoice',
    'invoice_sent' => 'Invoice sent successfully',
    'invoice_cancelled' => 'Invoice cancelled successfully',
    'cannot_cancel_paid_invoice' => 'Cannot cancel a paid invoice',

    // Time Entries
    'time_entry_not_found' => 'Time entry not found',
    'time_entry_updated' => 'Time entry updated successfully',

    // Attendance
    'no_attendance_record' => 'No attendance record found for today',
    'clocked_out' => 'Clocked out successfully',
    'attendance_not_found' => 'Attendance record not found',
    'attendance_updated' => 'Attendance record updated successfully',
    'attendance_created' => 'Attendance record created successfully',
    'attendance_already_exists' => 'Attendance record already exists for this employee on this date',

    // Access Control Devices
    'devices_retrieved' => 'Access control devices retrieved successfully',
    'device_retrieved' => 'Access control device retrieved successfully',
    'device_created' => 'Access control device created successfully',
    'device_updated' => 'Access control device updated successfully',
    'device_deleted' => 'Access control device deleted successfully',
    'device_not_found' => 'Access control device not found',
    'device_connection_ok' => 'The device is reachable and the credentials are valid',
    'device_unreachable' => 'The device could not be reached. Check the IP address, the port and the network route.',
    'device_auth_failed' => 'The device rejected the credentials. Check the username and the password.',
    'device_endpoint_unsupported' => 'The device does not support this ISAPI endpoint.',
    'device_request_failed' => 'The device rejected the request (status :status).',
    'device_invalid_payload' => 'The device sent a payload that could not be read.',
    'device_event_accepted' => 'Device event accepted',
    'device_users_pushed' => 'The employees were pushed to the device',
    'device_users_pulled' => 'The people stored on the device were retrieved',
    'device_user_linked' => 'The employee was linked to the device',
    'device_user_unlinked' => 'The employee was unlinked from the device',
    'device_polled' => 'The device was polled for new events',
    'device_events_retrieved' => 'The device events were retrieved',
    'device_webhook_enabled' => 'The device now pushes its events to the ERP',
    'device_webhook_disabled' => 'The device no longer pushes its events to the ERP',

    // Task Permissions
    'no_permission_manage_team' => 'You do not have permission to manage team members on this task type.',
    'no_permission_manage_test_scenarios' => 'You do not have permission to manage test scenarios on this task type.',
    'no_permission_verify_test_scenarios' => 'You do not have permission to verify test scenarios. Only Admin and users assigned as Reviewer in the task team can verify.',

    // Project Permissions
    'no_permission_view_budget' => 'You do not have permission to view project budget information.',
    'no_permission_view_history' => 'You do not have permission to view project history.',

    // Task Deliverables
    'deliverables_retrieved' => 'Task deliverables retrieved successfully',
    'deliverable_retrieved' => 'Deliverable retrieved successfully',
    'deliverable_created' => 'Deliverable created successfully',
    'deliverable_updated' => 'Deliverable updated successfully',
    'deliverable_deleted' => 'Deliverable deleted successfully',
    'deliverable_submitted' => 'Deliverable submitted for review successfully',
    'deliverable_reviewed' => 'Deliverable reviewed successfully',
    'deliverable_cannot_update' => 'Cannot update deliverable in current status',
    'deliverable_cannot_delete' => 'Cannot delete deliverable in current status',
    'deliverable_cannot_submit' => 'Cannot submit deliverable in current status',
    'deliverable_cannot_review' => 'Cannot review deliverable in current status',

    // Admin Deliverable Management
    'admin_deliverable_updated' => 'Deliverable updated successfully by admin',
    'admin_deliverable_deleted' => 'Deliverable deleted successfully by admin',
    'admin_status_changed' => 'Deliverable status changed successfully',
    'admin_deliverable_reset' => 'Deliverable reset to pending status successfully',
    'admin_deliverable_approved' => 'Deliverable force approved successfully',
    'admin_deliverable_rejected' => 'Deliverable force rejected successfully',

    // Activities
    'activities_retrieved' => 'Activities retrieved successfully',
    'activity_created' => 'Activity created successfully',
    'activity_retrieved' => 'Activity retrieved successfully',
    'activity_updated' => 'Activity updated successfully',
    'activity_completed' => 'Activity marked as complete successfully',
    'activity_deleted' => 'Activity deleted successfully',

    // Contracts
    'contracts' => [
        'retrieved' => 'Contracts retrieved successfully',
        'generated' => 'Contract generated successfully',
        'signed' => 'Contract signed successfully',
        'status_updated' => 'Contract status updated successfully',
        'already_exists' => 'Contract already exists for this quotation',
        'deleted' => 'Employment contract deleted successfully',
    ],

    // Analytics
    'sales_performance_retrieved' => 'Sales performance metrics retrieved successfully',
    'rejection_analysis_retrieved' => 'Rejection analysis retrieved successfully',
    'financial_forecast_retrieved' => 'Financial forecast retrieved successfully',
    'follow_up_radar_retrieved' => 'Follow-up radar retrieved successfully',
    'dashboard_summary_retrieved' => 'Dashboard summary retrieved successfully',
    'analytics_cache_cleared' => 'Analytics cache cleared successfully',
];
