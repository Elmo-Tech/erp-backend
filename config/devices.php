<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Access Control Devices
    |--------------------------------------------------------------------------
    |
    | Devices (Hikvision fingerprint / card terminals) are polled on a
    | schedule. The polling job reads the access control event log over
    | ISAPI and turns authorised punches into attendance records.
    |
    | The polling interval is the fallback cadence - a device may also be
    | configured to push events to the webhook endpoint, in which case the
    | push handler is the primary ingestion path.
    |
    */

    'polling' => [
        'enabled' => env('DEVICE_POLLING_ENABLED', true),
        'interval_minutes' => (int) env('DEVICE_POLL_INTERVAL_MINUTES', 5),
        'lookback_minutes' => (int) env('DEVICE_POLL_LOOKBACK_MINUTES', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook (push mode)
    |--------------------------------------------------------------------------
    |
    | Devices can push events to /api/v1/device-events/{token}. The token is
    | generated per device and stored on the device record.
    |
    */

    'webhook' => [
        'enabled' => env('DEVICE_WEBHOOK_ENABLED', true),
        'rate_limit_per_minute' => (int) env('DEVICE_WEBHOOK_RATE_LIMIT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | ISAPI Client
    |--------------------------------------------------------------------------
    |
    | Shared behaviour for the ISAPI (Hikvision) client: request timeout,
    | TLS verification and the default employee-number template used when
    | provisioning employees onto a terminal.
    |
    */

    'isapi' => [
        'timeout' => (int) env('DEVICE_ISAPI_TIMEOUT', 15),
        'connect_timeout' => (int) env('DEVICE_ISAPI_CONNECT_TIMEOUT', 5),
        'verify_tls' => env('DEVICE_ISAPI_VERIFY_TLS', false),
        'user_verify_mode' => env('DEVICE_USER_VERIFY_MODE', 'fpOrCard'),
        'door_right' => env('DEVICE_DOOR_RIGHT', '1'),
        'belong_group' => env('DEVICE_BELONG_GROUP', '1'),
        'validity_years' => (int) env('DEVICE_VALIDITY_YEARS', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Employee provisioning
    |--------------------------------------------------------------------------
    |
    | How the ERP employee number is rendered when it is pushed to a
    | terminal as the device employee number.
    |
    */

    'employee_no' => [
        'prefix' => env('DEVICE_EMPLOYEE_NO_PREFIX', ''),
        'fallback_to_user_id' => env('DEVICE_EMPLOYEE_NO_FALLBACK_USER_ID', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Event interpretation
    |--------------------------------------------------------------------------
    |
    | The terminal reports the outcome of an authentication attempt in
    | different fields depending on the firmware and the event source.
    | These lists let an administrator tune the classification without
    | touching code.
    |
    | `denied_minor_codes` are the ISAPI "minor" event codes that represent
    | a rejected attempt. `authorized_status_values` are the `statusValue`
    | codes that represent a granted attempt - when the list is empty the
    | service falls back to a textual scan of the status fields.
    |
    */

    'denied_minor_codes' => array_filter(explode(',', (string) env('DEVICE_DENIED_MINOR_CODES', ''))),

    'authorized_status_values' => array_filter(explode(',', (string) env('DEVICE_AUTHORIZED_STATUS_VALUES', ''))),

];
