<?php

return [
    'retrieved' => 'Proposals retrieved successfully',
    'created' => 'Proposal created successfully',
    'updated' => 'Proposal updated successfully',
    'deleted' => 'Proposal deleted successfully',
    'not_found' => 'Proposal not found',
    'validation_failed' => 'Validation failed for proposal',
    'cannot_edit' => 'This proposal cannot be edited',
    'cannot_delete' => 'This proposal cannot be deleted',
    'file_uploaded' => 'File uploaded successfully',
    'sent' => 'Proposal sent successfully',
    'approved' => 'Proposal approved successfully',
    'rejected' => 'Proposal rejected',
    'fast_track_created' => 'Fast track proposal created successfully',
    'expiring_retrieved' => 'Expiring proposals retrieved successfully',

    // Notification translations
    'view_action' => 'View Proposal',

    // Sent notification
    'sent_notification_subject' => 'Proposal Sent: :title',
    'sent_notification_line' => 'Proposal ":title" has been sent to lead ":lead".',
    'sent_notification_footer' => 'Please follow up with the client for their response.',
    'sent_notification_message' => 'Proposal ":title" has been sent',

    // Approved notification
    'approved_notification_subject' => 'Proposal Approved: :title',
    'approved_notification_line' => 'Great news! Proposal ":title" for lead ":lead" has been approved.',
    'approved_notification_footer' => 'You can now proceed with creating a quotation.',
    'approved_notification_message' => 'Proposal ":title" has been approved',

    // Rejected notification
    'rejected_notification_subject' => 'Proposal Rejected: :title',
    'rejected_notification_line' => 'Proposal ":title" for lead ":lead" has been rejected.',
    'rejection_reason' => 'Reason: :reason',
    'rejected_notification_footer' => 'Please review the feedback and consider revising the proposal.',
    'rejected_notification_message' => 'Proposal ":title" has been rejected',

    // Expiring notification
    'expiring_notification_subject' => 'Proposal Expiring Soon',
    'expiring_notification_line' => 'Proposal ":title" for lead ":lead" expires :expiry.',
    'expiring_notification_footer' => 'Please follow up with the client before the proposal expires.',
    'expiring_notification_message' => 'Proposal ":title" expires in :days days',
];
