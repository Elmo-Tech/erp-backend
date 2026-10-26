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
  Dashboard and Active Timers now subscribe to Reverb events instead of polling
  every 10 seconds. Private timer state also stops polling while subscribed.
  A visible-page/online fallback runs once per minute only while the corresponding
  private subscription is unavailable. Subscription/reconnection, visibility and
  network return trigger a fresh authorized snapshot.
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
- Active Timers elapsed durations advance with a local display clock, without
  fetching the API just to increment the duration.
- Worker responses have explicit JavaScript MIME type, root scope permission and
  no-store cache headers. Registration bypasses update caches, checks HTTP/MIME,
  bounds activation waiting and distinguishes browser registration failures from
  missing/misrouted deployment assets. Notification-only worker updates activate
  immediately; the worker does not cache application pages or API data.

## Realtime monitoring contract

- New private channel: `work-monitoring`; event alias: `work.activity.changed`.
  It is authorized by `WorkMonitoringPolicy`: active users with
  `view_all_time_entries`, or admin/super-admin users with dashboard access. A role
  name alone does not authorize the channel. The frontend uses the same eligibility.
- The shared payload contains only `changeId` and an internal `reason`. It does
  not broadcast employee, session, task or project records. The event invalidates
  dashboard/Active Timers queries; existing API authorization still controls all
  returned data. Nearby events are batched for 150 ms to avoid redundant fetches.
- Start/resume, pause, hold, end, admin stop, stale-session expiration, manual time
  corrections and task/project lifecycle changes notify monitoring clients after
  commit. Rollbacks send no invalidation. Heartbeats do not emit monitoring events.
- Deleting a project captures affected active/paused timer owners before bulk SQL
  deletion and sends each a private `timer.updated` invalidation. Task/project
  deletion, restoration and renaming also resynchronize affected owners. This
  clears stale shortcuts across browsers, not just the browser performing deletion.
- The subscription is considered healthy only after its private-channel ack,
  not merely a connected WebSocket. Disconnects and authorization failures keep
  the one-minute fallback active. Reconnection refetches missed state; hidden or
  offline pages defer refresh until visible/online. Late timer snapshots cannot
  overwrite newer local state or a different signed-in user's timer.
- These events use synchronous broadcasting with failure logging, so committed
  work remains successful if Reverb is temporarily unavailable.

## Redeployment

1. Commit/push backend and frontend changes, including the new realtime event,
   policy, publisher, subscription helper and monitoring hook, to the deployed branch.
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
7. Verify backend `BROADCAST_CONNECTION=reverb`; the backend publishes internally
   to `127.0.0.1:8080` over HTTP. Frontend `NEXT_PUBLIC_REVERB_*` build variables
   must point to the public WebSocket host on port 443/HTTPS, using the same app
   key. Rebuild the frontend if these public build variables change. Keep the
   existing all-in-one scheduler/worker/Reverb setup.
8. With two authorized browsers open, start/pause/resume/stop a disposable timer
   in one and verify monitoring updates in the other. DevTools should show the
   private subscription and `work.activity.changed` frames. With no changes and a
   healthy subscription, there should be no periodic dashboard/active-timer GETs.
   The separate 45-second session heartbeat remains expected while working.
   Disconnect Reverb: visible, online clients fall back once per minute. Restore
   it: private-channel re-ack triggers synchronization and stops fallback GETs.

## Verification

- Full SQLite backend suite: 456 tests pass.
- Real isolated PostgreSQL 18.4: 31 focused realtime/deployment/timer/client tests
  pass after reproducing the old permanent-delete SQL failure first.
- Frontend: 68 test files / 216 tests pass; TypeScript and production build pass.
- Realtime tests verify channel authorization, after-commit/rollback behavior,
  lifecycle/deletion delivery, quiet healthy subscriptions, reconnect/authorization
  failure fallback, hidden/offline recovery, permission cleanup and local duration
  ticking without polling. Targeted Pint/ESLint and diff whitespace checks pass.
- A real isolated local Reverb server accepted private subscriptions and delivered
  Laravel broadcaster messages for both monitoring and personal timer invalidations
  over WebSocket, using disposable credentials and no production data.
- Earlier production-build browser QA with mocked APIs verified immediate dashboard
  resume/end updates, hidden/direct-redirected Reports, service-worker headers and
  registration, notification popup deduplication and existing employee/trash flows.
