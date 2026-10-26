# Production follow-up — 26 October 2026

## Confirmed findings

- Soft-delete requests from the frontend included `confirmation: ""`. Laravel
  converted it to null, then applied a string rule intended only for permanent
  deletion. The field is now excluded unless permanent deletion is requested;
  new frontend requests omit the unused field entirely.
- Permanent deletion failed on real PostgreSQL with `operator does not exist:
  text ->> unknown`: `notifications.data` is a TEXT column. Related notifications
  are now decoded in bounded batches, supporting numeric/string IDs and preserving
  unrelated or malformed legacy payloads. No JSON column conversion is needed.
- Dashboard data refreshed every 30 seconds without invalidating on timer changes.
  Its query also excluded open timers for deleted tasks/projects. Dashboard and
  Active Timers now use the same open-work source, with explicit target notices.
- Public `/push-sw.js` returned HTTP 200 and JavaScript. Registration succeeded
  against the public site in clean Chrome and Helium profiles. The reported
  browser-profile fetch failure was not reproduced; no production data was changed.

## Changes

- Standalone Reports navigation is removed; `/dashboard/admin` redirects to the
  main dashboard. Administrative user/audit subpages retain their separate routes.
- Own timer lifecycle changes invalidate dashboard and monitoring queries at once.
  Both monitoring views refresh every 10 seconds, and private timer state has a
  visible-page/online HTTP fallback every 30 seconds when realtime is unavailable.
  Successful project deletion also resynchronizes the navbar timer and monitoring
  caches immediately, removing shortcuts for work that has just been deleted.
- Open orphaned/archived work remains visible with its target status. Ending it
  closes and saves the work record without marking deleted work complete. Resuming
  deleted/archived work is rejected with an explanation; unavailable targets are
  excluded from the resumable shortcut until restored.
- Realtime delivery failures are logged instead of returning a 500 after a timer
  change has already committed. Repeated End requests return a descriptive 409;
  frontend state is resynchronized. Unexpected completion errors have a user-facing
  explanation and server-side exception reporting.
- Worker responses have explicit JavaScript MIME type, root scope permission and
  no-store cache headers. Registration bypasses update caches, checks HTTP/MIME,
  bounds activation waiting and distinguishes browser registration failures from
  missing/misrouted deployment assets. Notification-only worker updates activate
  immediately; the worker does not cache application pages or API data.

## Redeployment

1. Commit/push backend and frontend changes, including the new `ActiveWorkService`
   and frontend `desktopPush` helper, to the deployed branch.
2. Redeploy the backend, then rebuild/redeploy the frontend. The current all-in-one
   `start.sh` restarts API/worker/Reverb/scheduler with the new code.
3. These fixes add no database migrations or new environment variables.
4. Reload existing browser tabs after deployment. Check `/push-sw.js` returns
   JavaScript and `Service-Worker-Allowed: /`.
5. Retry desktop registration. If only an existing browser profile fails, inspect
   the new browser technical details and site extensions/settings. Unregistering
   this site's old worker in DevTools → Application → Service Workers, then reloading
   and enabling notifications again, can clear stale registrations. Avoid clearing
   unrelated site data or replacing the application's encryption key.
6. Verify resume/end updates both counters immediately; legacy deleted targets
   remain visible with a notice and can be stopped safely. Verify soft/permanent
   deletion on a disposable project before using the permanent action on real work.

## Verification

- Full SQLite backend suite: 448 tests pass.
- Real isolated PostgreSQL 18.4: 23 focused deployment/timer/client regression tests
  pass after reproducing the old permanent-delete SQL failure first.
- Frontend: 66 test files / 208 tests pass; TypeScript and production build pass.
- Production-build browser QA with mocked APIs verifies immediate dashboard
  resume/end updates, hidden/direct-redirected Reports, service-worker headers and
  registration, notification popup deduplication and existing employee/trash flows.
