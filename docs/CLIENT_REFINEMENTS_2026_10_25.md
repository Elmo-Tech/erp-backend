# Client refinements — deployment and operating notes

## Deploy

1. Install locked backend dependencies: `composer install --no-dev --optimize-autoloader`.
2. Run `php artisan migrate --force`. This release adds:
   - `2026_10_25_000001_track_leave_balance_overrides.php`
   - `2026_10_25_000002_add_employee_personal_details.php`
   - `2026_10_25_000003_add_web_push_and_timer_preferences.php`
   - `2026_10_25_000004_add_page_and_action_permissions.php`
   The existing access-device migrations must also be deployed if not already applied.
3. Generate VAPID keys **once** with `php artisan notifications:vapid`, and store
   the output in the server's environment. Keep the private key private and stable
   across deployments. Set `WEB_PUSH_SUBJECT` to a real company contact, and
   `FRONTEND_URL` to the HTTPS application URL. Never generate new keys on each boot.
4. Keep the Laravel scheduler (every minute), queue worker, and Reverb running;
   restart long-running workers after deploying the code. Browser-independent
   reminders use the scheduler and queue, not a web page timer.
5. Build/deploy the frontend, including the root static file `/push-sw.js`.
   Configure `NEXT_PUBLIC_REVERB_APP_KEY`, `NEXT_PUBLIC_REVERB_HOST`, port and
   scheme before the frontend build for realtime notifications. Missing realtime
   configuration no longer crashes the dashboard; local timer reminders still work.
   Each user enables desktop notifications from Profile or the timer bell and
   chooses a 5/10/15/30/45/60-minute interval.
6. Review Roles & Permissions. The migration adds new grants to the standard
   admin/super-admin roles; it does not reset existing role assignments or seed QA
   accounts. Existing HR master grants remain effective. Custom roles can use the
   individual page/section/action permissions instead.

The changes have not been deployed to the hosted server by this development task.

## Leave allowances

- Changing a type's allowance updates **current-year**, non-custom balances for
  that type, preserving consumed days and previous years.
- Individual profile allocations are explicit overrides. “Use company default”
  removes the override without resetting consumption.
- An allocation below consumed days shows no remaining days and an overdrawn
  amount; it does not rewrite historical leave or invent extra allowance.
- The migration conservatively treats existing allocations different from the
  current type default as custom, because historical manual overrides cannot be
  distinguished reliably. Review these once and select “Use company default” for
  employees intended to inherit the company's allowance.
- Review controls show Approve and Reject. Unreviewed requests remain recorded
  internally until a decision; administrative paid registration and approval still
  consume balance transactionally. Historical cancellations retain their audit trail.

## Permissions

`manage_employees` remains a master HR grant for compatibility. To restrict an HR
user to a section, remove that master grant and select action-specific permissions,
for example `create_leaves` and/or `approve_leaves`. A create-only grant cannot
approve a record. Section actions grant access to the records needed for those
actions, while salary summaries/reports have separate access.

`manage_lookup_options` is the master grant for list settings; each setting tab
also has its own manage/create/edit/delete/reorder grants. Role editing retains
privilege ceilings and protected-role checks. The frontend refreshes permissions
periodically and on focus; the API checks them on every request.

Employees can view their own personal details, contracts/files, and request history.
Action-only HR grants do not reveal national ID, address, birthday or contact data;
those require employee viewing/editing access, or ownership of the profile.
Administrative mutation of peers or higher-privilege users still follows the
existing hierarchy rules. A user with `manage_employee_salary` can update the
current salary from the salary tab; contract and employee salary are synchronized
atomically, while previously approved financial snapshots are preserved.

## Desktop delivery and timers

`TIMER_EXPIRE_ON_DISCONNECT=false` is the new default: closing a page does not stop
its work timer. Set it to true only if deliberately restoring the old heartbeat
expiry policy. Explicit pause/end and administrative stopping remain available.

Desktop Web Push reaches supported browsers with no app page open. Delivery when
the entire browser has been quit depends on browser/OS background support, site
permission, battery settings and notification policy. A powered-off device cannot
display notifications. Test actual company browser/device combinations; this is
not a guarantee of delivery after a complete browser/OS shutdown.

General project/task database notifications feed desktop push. Leave and short-
permission requests notify eligible reviewers; decisions notify the employee.
Timer reminders are ephemeral and do not fill the saved notification list. Push
subscriptions are per-browser and removed on normal sign-out. Expired subscriptions
are removed after the push service reports expiry.

Work/supervision heartbeats are silent. Generic CRUD feedback has one API owner,
and custom Drive/deliverable/supervision feedback explicitly opts out of the
generic toast, preventing duplicate messages from the transport and the screen.

## Project deletion

- Soft deletion retains project records and makes them available in Project Trash.
  Running work timers must be stopped before moving a project to trash.
- Restore and permanent deletion have separate permissions.
- Permanent deletion requires typing the exact project name. It removes owned
  project/task records, polymorphic work timers, associated notifications and
  project/task and owned review/test/deliverable point entries. Local project uploads are cleaned by a
  retryable queued job after the database transaction commits.
- External links/shared Google Drive folders are disconnected, not recursively
  erased: their externally shared ownership is not established by a project link.
- A used project type is blocked from deletion, with linked project names and
  instructions to reassign those projects or deactivate the type. Trashed projects
  also retain their type association.

## Office LAN terminal

See [LAN_DEVICE_BRIDGE.md](LAN_DEVICE_BRIDGE.md) for the outbound read-only bridge,
shadow verification, activation, retry/spool behavior and private-VPN alternative.
Physical terminal configuration and live validation remain site-specific.

## Verification

- Backend: 438 tests pass; LAN bridge: 3 simulated tests pass.
- Frontend: 64 test files / 199 tests pass; TypeScript and production build pass.
- Production-browser QA uses mocked API responses and checks login/toast behavior,
  employee data, allocation updates, permission-specific controls, own profile,
  guarded permanent deletion, timer popup/duplicate suppression, silent heartbeats
  and a 390px layout with an active timer.
- Changed/new frontend files pass ESLint; changed PHP files pass Pint. Both
  repositories pass `git diff --check`.
- Repository-wide checks also expose existing baseline issues: full ESLint reports
  five errors in unchanged `taskSupervision.ts`, `EmployeeAvatarField.tsx`,
  `employee-partial-pays.ts` and `TaskSupervisionSection.tsx`; full Pint reports
  legacy formatting issues in unrelated PHP files. Changed-file checks are tracked
  separately from these repository-wide baselines.
