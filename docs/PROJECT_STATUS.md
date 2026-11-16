# Project Status — Source of Truth

**Last documented:** 2026-11-16 (P0-01 working session, draft PRs). This is a snapshot, not a live status feed. Every task owner MUST update it after a completed or interrupted session, using real git/CI evidence.

## Current milestone

**Phase 0 — Foundation / P0-01 IN PROGRESS, NOT VERIFIED.**
The initial Laravel 13 and Vite React skeletons are on `rebuild/erp-v2`. Foundational migrations and unit/health test source files exist, but successful execution has NOT been confirmed.

| Checkpoint | Status | Evidence |
|---|---|---|
| Backend integration branch | Created | `9b4703422978bb04d244c598adf2832ee0493f44` (pre-handoff) |
| Frontend integration branch | Created | `e552e3552ab68f824e4fb44d95d630b578b4ce92` (pre-handoff) |
| Laravel 13 API scaffold | Code committed; unverified | `README.md`, `bootstrap/app.php`, `routes/api.php` |
| React/Vite UI shell | Code committed; unverified | Frontend `src/App.tsx` |
| Company + GL foundation migrations | Written; **not migration-tested** | Backend `database/migrations/` |
| Docker and CI files | Written; **not build-tested** | `Dockerfile`, `compose.yaml`, workflows |
| Backend GitHub Actions | Latest PR CI completed Composer install, PostgreSQL 18 migrations and PHPUnit, Docker stage **in progress** at this checkpoint | https://github.com/Elmo-Tech/erp-backend/actions/runs/43857654487 |
| Frontend GitHub Actions | **SUCCESS** PR run (typecheck, 4 Vitest, build, Docker/Nginx smoke) | https://github.com/Elmo-Tech/erp-frontend/actions/runs/43857835260 |
| Auth/RBAC and complete financial posting | **NOT IMPLEMENTED** | Scheduled in P0-02 / P0-04 |
| Staging and production | **NOT DEPLOYED** | Requires release approval |

GitHub connector log requests for those initial failures yielded no actionable logs; **root cause unknown**. Current branch heads, new CI runs and Issue states must be rechecked before work.

## Active / next task
**[P0-01 — CI + reproducible builds](https://github.com/Elmo-Tech/erp-backend/issues/1) — OPEN, IN PROGRESS, NOT VERIFIED.**
Start at [handoffs/P0-01.md](handoffs/P0-01.md). First goal: obtain real CI errors or reproduce locally, commit authentic lockfiles, validate migrations and app builds, and get passing CI runs for both repos.

## Blockers / gates
- Frontend CI PASS on task branch; Backend CI Docker stage still in progress at this checkpoint; overall P0-01 NOT VERIFIED.
- Authentic Composer and npm lockfiles generated on GitHub-hosted runners and committed on task branches. Dependency/build verification tracked below.
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

## P0-01 working session — 2026-11-16 (evidence-backed)

- Backend HEAD before this task: `2479f339d1702da22a9bcad4aac279451ebe1a24`; frontend HEAD: `341fc7cd8abd98b6c7865c754984d26bb68dd111`. No pre-existing PRs found during review.
- Created **topic branches, not integration commits**: `task/p0-01-ci-baseline` in both repositories.
- Draft PRs: [backend #2](https://github.com/Elmo-Tech/erp-backend/pull/2) and [frontend #1](https://github.com/Elmo-Tech/erp-frontend/pull/1). Backend code commit: [`9108692`](https://github.com/Elmo-Tech/erp-backend/commit/9108692309176723a39f1ce13440166df82838af); frontend commit: [`c3ab5d4`](https://github.com/Elmo-Tech/erp-frontend/commit/c3ab5d45dd0b48111066a0aee877f8c7a4d4a639).
- Backend change: explicit Composer lockfile gate, PostgreSQL 18 disposable migration + test runner, Docker image and health smoke steps, company-access and composite-FK negative tests.
- Frontend change: explicit npm lockfile gate, `npm ci` in CI and Docker, Docker/Nginx smoke, frontend health-request unit tests, `.dockerignore`.
- Last integrated CI runs: [backend **failure**](https://github.com/Elmo-Tech/erp-backend/actions/runs/43846548484) and [frontend **failure**](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43846673824). Both lasted approximately 3–4 seconds, with no reported job steps. Log downloads returned GitHub BlobNotFound; 2 check annotations per job could not be fetched through the connector. **Root cause NOT VERIFIED.**
- Newly triggered draft PR workflows, status checked once while **in_progress**: [backend run](https://github.com/Elmo-Tech/erp-backend/actions/runs/43857111861), [frontend run](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43857118913). Passing CI **NOT VERIFIED**.
- Static checks locally: `php -l tests/Feature/CompanyIsolationTest.php` passed, Python YAML parser loaded both CI workflows; these are **not PHPUnit/migration/build results**.
- Runtime blockers in isolated local container: no Composer executable, Docker daemon/binary or PostgreSQL 18; no outbound DNS to GitHub/registries. Do not handcraft lockfiles. `composer.lock` / `package-lock.json` **NOT GENERATED** and CI workflows intentionally fail at lockfile gates once runner starts.
- **Risk:** no CI, PHPUnit, PostgreSQL migration, Docker startup, typecheck, Vitest, real backend API-to-frontend connectivity or security acceptance criteria proven. Draft PRs must not merge or deploy.
- **Immediate next action inside P0-01:** inspect Actions annotation text in GitHub UI / runner availability; generate authentic Composer 2 and npm 10 lockfiles with registry access on this same task branch, rerun test/migration/build checks and attach green-run URLs. **Only after the P0-01 acceptance gate** start the next task, **P0-02 — Auth/RBAC**.

### P0-01 update after authenticated runner bootstrap (2026-11-16)
- **Authentic lockfiles committed:** [backend `composer.lock`](https://github.com/Elmo-Tech/erp-backend/blob/task/p0-01-ci-baseline/composer.lock) generated by Composer 2 on GitHub-hosted PHP 8.4, and [frontend `package-lock.json`](https://github.com/Elmo-Tech/erp-frontend/blob/task/p0-01-ci-baseline/package-lock.json) generated by npm 11.21.2 (lockfile v3). No lockfile content was fabricated; temporary bootstrap workflows were removed from both task branches.
- **Frontend CI SUCCESS**: [run #43857835260](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43857835260), checks include `npm ci`, typecheck, **4 Vitest tests**, Vite build, Docker image and Nginx container smoke. npm 10.9.9 on the runner had failed during dependency resolution with `Cannot read properties of null (reading 'edgesOut')`; npm 11.21.2 succeeded, pinned in frontend CI and Docker build.
- **Backend CI partial verified**: [run #43857654487](https://github.com/Elmo-Tech/erp-backend/actions/runs/43857654487) completed Composer validation/install, PostgreSQL 18 migration and PHPUnit stages successfully; Docker build was in progress during this documentation update. Full run SUCCESS and API health smoke **NOT YET VERIFIED**.
- **Previous working-session text above mentioning missing locks and still-pending frontend CI is superseded by this newer verified checkpoint.** Initial integration failures remain unexplained because the corresponding old logs were inaccessible; current task CI failures due to missing lockfile gates were reproduced and fixed.
- Task **OPEN / IN PROGRESS**, draft PR review still required. No changes to `rebuild/erp-v2`, `main`, `oberation`, Production or customer data.
