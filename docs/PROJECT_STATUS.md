# Project Status — Source of Truth

**Last documented:** 2026-11-16. This is a snapshot, not a live status feed. Every task owner MUST update it after a completed or interrupted session, using real git/CI evidence.

## Current milestone

**Phase 0 — Foundation / P0-01 scaffold created, NOT verified.**
The initial Laravel 13 and Vite React skeletons are on `rebuild/erp-v2`. Foundational migrations and unit/health test source files exist, but successful execution has NOT been confirmed.

| Checkpoint | Status | Evidence |
|---|---|---|
| Backend integration branch | Created | `9b4703422978bb04d244c598adf2832ee0493f44` (pre-handoff) |
| Frontend integration branch | Created | `e552e3552ab68f824e4fb44d95d630b578b4ce92` (pre-handoff) |
| Laravel 13 API scaffold | Code committed; unverified | `README.md`, `bootstrap/app.php`, `routes/api.php` |
| React/Vite UI shell | Code committed; unverified | Frontend `src/App.tsx` |
| Company + GL foundation migrations | Written; **not migration-tested** | Backend `database/migrations/` |
| Docker and CI files | Written; **not build-tested** | `Dockerfile`, `compose.yaml`, workflows |
| Backend GitHub Actions | **FAILURE** initial run | https://github.com/Elmo-Tech/erp-backend/actions/runs/43843737400 |
| Frontend GitHub Actions | **FAILURE** initial run | https://github.com/Elmo-Tech/erp-frontend/actions/runs/43843105601 |
| Auth/RBAC and complete financial posting | **NOT IMPLEMENTED** | Scheduled in P0-02 / P0-04 |
| Staging and production | **NOT DEPLOYED** | Requires release approval |

GitHub connector log requests for those initial failures yielded no actionable logs; **root cause unknown**. Current branch heads, new CI runs and Issue states must be rechecked before work.

## Active / next task
**[P0-01 — CI + reproducible builds](https://github.com/Elmo-Tech/erp-backend/issues/1) — OPEN, ready for a new chat.**
Start at [handoffs/P0-01.md](handoffs/P0-01.md). First goal: obtain real CI errors or reproduce locally, commit authentic lockfiles, validate migrations and app builds, and get passing CI runs for both repos.

## Blockers / gates
- No verified passing Backend/Frontend CI.
- No checked Composer/npm dependency lockfiles; do NOT invent them.
- PostgreSQL 18 migration/integration tests, Docker smoke tests not verified.
- API session authentication/MFA/company permissions have not been implemented.
- Full double-entry posting checks, immutable journal guards, accountant tax review and production legal names remain pending.

## What to do after each task
1. Update this page with date, exact status, evidence links (Issue/PR/CI), and next task.
2. Update [MODULE_ROADMAP.md](MODULE_ROADMAP.md) where module status changed.
3. Add/update `docs/handoffs/<TASK-ID>.md`, even if blocked.
4. Add an ADR to [DECISIONS.md](DECISIONS.md) if an actual architecture or business decision was taken.
5. Report **verified vs unverified** and don't equate a commit with a passed test.

**Project status is not "done" until its acceptance criteria are evidenced.**
