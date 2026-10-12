# End-to-end user-flow configuration

For local or automated browser runs, use `APP_ENV=local` or `APP_ENV=testing`
and `API_RATE_LIMIT=1200`. `AUTH_RATE_LIMIT=10` limits failed credential or
validation attempts per email/IP per minute; successful sign-ins do not consume
that allowance. Both limits are configurable. After changing environment values,
run `php artisan config:clear` (or rebuild the configuration cache on deployment).

Default QA accounts are seeded by `AdminUserSeeder`. The admin and manager role
permission sets include `view_admin_stats`; use an authorized account for Reports.
Short permissions can be created and approved from Attendance Management's
**Review short permissions** action. Its pending queue covers all dates and
employees in the current reviewer's scope, including requests entered by that
reviewer. Admin and super-admin accounts with `manage_permissions` (or legacy
`manage_employees`) may approve their own short permissions. Other employees'
own requests require another authorized approver. **Mark as Paid** opens approval
with paid compensation selected; **Approve** allows choosing paid or unpaid.

Paid leave balances consume working days on approval. My Requests shows pending
working days separately so a submitted request does not look like missing data.

Deduction-type creation is intentionally excluded from the simplified ERP UI;
the backend continues to block the advanced-deductions module. Exclude
`Create an active deduction type` from simplified-mode suites.

Google Drive's frontend callback is `/auth/google/callback`. Set
`GOOGLE_DRIVE_REDIRECT_URI` to the full frontend URL with that path and register
the same URL with Google. The previous
`/dashboard/integrations/google-drive/callback` route is also supported.
Successful authorization returns to the local dashboard destination in `state`,
or the Google Drive settings tab. Missing, cancelled, or invalid authorization
returns to that tab with a visible failure message. A synthetic `TEST_CODE`
requires a mocked Google token exchange to exercise successful authorization.
