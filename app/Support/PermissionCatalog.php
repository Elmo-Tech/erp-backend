<?php

namespace App\Support;

class PermissionCatalog
{
    public const HR_ACTIONS = [
        'employee_contracts' => ['view', 'create', 'edit', 'delete', 'upload', 'download'],
        'leaves' => ['view', 'create', 'edit', 'approve', 'reject', 'cancel', 'allocate'],
        'permissions' => ['view', 'create', 'edit', 'approve', 'reject', 'cancel'],
        'deductions' => ['view', 'create', 'approve', 'reject', 'cancel', 'evaluate', 'reset', 'override'],
        'overtime' => ['view', 'create', 'approve', 'reject', 'cancel'],
        'employee_documents' => ['view', 'upload', 'download', 'delete'],
        'employee_advances' => ['view', 'create', 'approve', 'reject', 'disburse', 'settle', 'cancel'],
    ];

    public static function definitions(): array
    {
        $definitions = self::ADDITIONS;
        foreach (self::HR_ACTIONS as $resource => $actions) {
            foreach ($actions as $action) {
                $definitions[$action.'_'.$resource] = self::group('manage_'.$resource);
            }
        }
        foreach (['specializations', 'task_categories', 'project_types', 'project_statuses', 'task_statuses', 'contract_durations'] as $resource) {
            foreach (['create', 'edit', 'delete', 'reorder'] as $action) {
                $definitions[$action.'_'.$resource] = self::ADDITIONS['manage_'.$resource];
            }
        }

        return $definitions;
    }

    public static function hrPermissions(): array
    {
        $permissions = self::HR_PERMISSIONS;
        foreach (self::HR_ACTIONS as $resource => $actions) {
            foreach ($actions as $action) {
                $permissions[] = $action.'_'.$resource;
            }
        }

        return array_unique($permissions);
    }

    public static function hrAction(string $managementPermission, ?string $method = null): string
    {
        $method ??= request()->route()?->getActionMethod() ?? 'index';
        $action = match ($method) {
            'store', 'storeType' => 'create', 'update', 'updateType', 'updateSalary' => 'edit',
            'destroy', 'deleteFile' => 'delete', 'uploadFile' => 'upload', 'downloadFile' => 'download',
            'updateBalance' => 'allocate', 'evaluate' => 'evaluate', 'reset' => 'reset',
            'override' => 'override', 'approve', 'reject', 'cancel', 'disburse', 'settle' => $method,
            default => request()->isMethod('GET') ? 'view' : 'manage',
        };
        if ($managementPermission === 'manage_employee_documents' && $method === 'store') {
            $action = 'upload';
        }

        return $action.'_'.substr($managementPermission, strlen('manage_'));
    }

    /** Explicitly grouped page, tab, and action permissions. Existing manage_* permissions remain master grants. */
    public const ADDITIONS = [
        'manage_devices' => 'Settings / Access devices',
        'delete_employees' => 'Employees / General', 'toggle_employee_status' => 'Employees / General',
        'view_deleted_projects' => 'Projects / Trash', 'restore_projects' => 'Projects / Trash', 'force_delete_projects' => 'Projects / Trash',
        'view_employee_salary' => 'Employees / Salary', 'manage_employee_salary' => 'Employees / Salary',
        'manage_employee_advances' => 'Employees / Withdrawals', 'view_employee_reports' => 'Employees / Reports',
        'view_employee_contracts' => 'Employees / Contracts', 'view_employee_documents' => 'Employees / Documents',
        'view_hr_overview' => 'Employees / Overview',
        'manage_lookup_options' => 'Settings / Lists', 'manage_google_drive' => 'Settings / Google Drive',
        'manage_specializations' => 'Settings / Specializations', 'manage_task_categories' => 'Settings / Task categories',
        'manage_project_types' => 'Settings / Project types', 'manage_project_statuses' => 'Settings / Project statuses',
        'manage_task_statuses' => 'Settings / Task statuses', 'manage_contract_durations' => 'Settings / Contract durations',
        'view_profile' => 'Personal / Profile', 'view_my_requests' => 'Personal / Requests',
    ];

    public const HR_PERMISSIONS = ['manage_employees', 'view_employees', 'create_employees', 'edit_employees', 'delete_employees', 'toggle_employee_status', 'manage_employee_contracts', 'view_employee_contracts', 'manage_employee_salary', 'view_employee_salary', 'manage_employee_advances', 'manage_leaves', 'manage_permissions', 'manage_deductions', 'manage_overtime', 'manage_employee_documents', 'view_employee_documents', 'manage_supervision', 'view_employee_reports', 'view_hr_overview'];

    public static function group(string $name): string
    {
        if (isset(self::ADDITIONS[$name])) {
            return self::ADDITIONS[$name];
        }
        foreach (['specializations', 'task_categories', 'project_types', 'project_statuses', 'task_statuses', 'contract_durations'] as $resource) {
            if (str_ends_with($name, '_'.$resource)) {
                return self::ADDITIONS['manage_'.$resource];
            }
        }
        foreach ([
            'employee_contract' => 'Employees / Contracts', 'leave' => 'Employees / Leave',
            'hr_settings' => 'Settings / HR policy', 'deduction' => 'Employees / Deductions',
            'overtime' => 'Employees / Overtime', 'employee_document' => 'Employees / Documents',
            'supervision' => 'Tasks / Supervision', 'permission' => 'Employees / Short permissions',
            'role' => 'Administration / Roles', 'protected_user' => 'Administration / Privilege management',
            'user' => 'Administration / Users', 'employee' => 'Employees / General',
            'project_file' => 'Projects / Files', 'project_member' => 'Projects / Team',
            'project_history' => 'Projects / History', 'workflow' => 'Projects / Workflow',
            'wiki' => 'Projects / Wiki', 'project' => 'Projects / General',
            'task' => 'Tasks / General', 'time_entr' => 'Time tracking', 'timer' => 'Time tracking',
            'attendance' => 'Attendance', 'client' => 'Clients', 'dashboard' => 'Dashboard',
            'report' => 'Reports', 'stats' => 'Reports', 'audit' => 'Administration / Audit',
            'device' => 'Settings / Access devices', 'tag' => 'Settings / Tags',
        ] as $part => $group) {
            if (str_contains($name, $part)) {
                return $group;
            }
        }

        return 'Other';
    }

    public static function lookupPermission(string $type): string
    {
        return match ($type) {
            'specialization' => 'manage_specializations', 'task_category' => 'manage_task_categories',
            'project_type' => 'manage_project_types', 'project_status' => 'manage_project_statuses',
            'task_status' => 'manage_task_statuses', 'contract_duration' => 'manage_contract_durations',
            default => 'manage_lookup_options',
        };
    }
}
