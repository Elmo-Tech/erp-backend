<?php

return [
    // Opt-in role partition. In production, credentials/process isolation needs
    // an independently reviewed broker; this flag enables CI-only integration.
    'partition_required' => filter_var(env('ERP_ROLE_PARTITION_REQUIRED', false), FILTER_VALIDATE_BOOLEAN),
    'mfa_connection' => env('ERP_MFA_DB_CONNECTION') ?: null,
];
