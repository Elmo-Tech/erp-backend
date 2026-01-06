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
    'cannot_create_invoice_for_paid_milestone' => 'Cannot create invoice for paid milestone',
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

    // Task Permissions
    'no_permission_manage_team' => 'You do not have permission to manage team members on this task type.',
    'no_permission_manage_test_scenarios' => 'You do not have permission to manage test scenarios on this task type.',

    // Project Permissions
    'no_permission_view_budget' => 'You do not have permission to view project budget information.',
    'no_permission_view_history' => 'You do not have permission to view project history.',
];
