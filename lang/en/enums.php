<?php

return [
    // User Specializations
    'frontend' => 'Frontend Developer',
    'backend' => 'Backend Developer',
    'fullstack' => 'Full Stack Developer',
    'mobile' => 'Mobile Developer',
    'devops' => 'DevOps Engineer',
    'ui_ux' => 'UI/UX Designer',
    'qa' => 'Quality Assurance',
    'project_manager' => 'Project Manager',
    'business_analyst' => 'Business Analyst',
    'data_scientist' => 'Data Scientist',

    // Point Transaction Types
    'Manual' => 'Manual',
    'manual' => 'Manual',
    'task_completion' => 'Task Completion',
    'attendance_bonus' => 'Attendance Bonus',
    'performance_bonus' => 'Performance Bonus',
    'late_penalty' => 'Late Penalty',
    'absence_penalty' => 'Absence Penalty',
    'quality_bonus' => 'Quality Bonus',
    'innovation_bonus' => 'Innovation Bonus',

    // Task Statuses
    'todo' => 'To Do',
    'in_progress' => 'In Progress',
    'in_review' => 'In Review',
    'testing' => 'Testing',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',

    // Project Statuses
    'draft' => 'Draft',
    'active' => 'Active',
    'on_hold' => 'On Hold',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',

    // Task Priorities
    'low' => 'Low',
    'medium' => 'Medium',
    'high' => 'High',
    'urgent' => 'Urgent',

    // Lead Statuses
    'new' => 'New',
    'contacted' => 'Contacted',

    // Audit Severity Levels
    'audit_severity' => [
        'info' => 'Information',
        'warning' => 'Warning',
        'critical' => 'Critical',
    ],
    'qualified' => 'Qualified',
    'proposal_sent' => 'Proposal Sent',
    'negotiation' => 'Negotiation',
    'won' => 'Won',
    'lost' => 'Lost',

    // Activity Types
    'activity_type' => [
        'call' => 'Call',
        'email' => 'Email',
        'meeting' => 'Meeting',
        'note' => 'Note',
        'status_change' => 'Status Change',
        'technical_discovery' => 'Technical Discovery',
    ],

    // Activity Status
    'activity_status' => [
        'scheduled' => 'Scheduled',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],

    // Lead Temperature
    'lead_temperature' => [
        'hot' => 'Hot',
        'hot_desc' => 'Ready to buy, high interest',
        'warm' => 'Warm',
        'warm_desc' => 'Interested, needs nurturing',
        'cold' => 'Cold',
        'cold_desc' => 'Low interest, long-term prospect',
    ],

    // Contract Status
    'contract_status' => [
        'draft' => 'Draft',
        'pending_signature' => 'Pending Signature',
        'signed' => 'Signed',
        'active' => 'Active',
        'completed' => 'Completed',
        'terminated' => 'Terminated',
    ],
];
