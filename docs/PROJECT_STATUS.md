# Project Status — Source of Truth

**Last verified snapshot:** 2026-11-16. GitHub is the canonical record; always re-check current branch heads and latest CI before beginning a new chat.

## Milestone

**Phase 0 — F00 platform CI baseline: automated checks PASS on both task branches; P0-01 is IN PROGRESS pending PR review and cross-repo connectivity evidence.** No production or staging deployment authorized.

| Checkpoint | Status | Evidence |
|---|---|---|
| Integration branches | Existing, unchanged by this task | Backend `2479f339d1702da22a9bcad4aac279451ebe1a24`; frontend `341fc7cd8abd98b6c7865c754984d26bb68dd111` before task |
| Dedicated task branches | Created in both repos | `task/p0-01-ci-baseline` (per repo; both based on `rebuild/erp-v2`) |
| Backend draft PR | OPEN, awaiting human review | https://github.com/Elmo-Tech/erp-backend/pull/2 |
| Frontend draft PR | OPEN, awaiting human review | https://github.com/Elmo-Tech/erp-frontend/pull/1 |
| Authentic dependency lockfiles | GENERATED and committed via hosted package managers | [Composer lock](https://github.com/Elmo-Tech/erp-backend/blob/task/p0-01-ci-baseline/composer.lock), [npm lock](https://github.com/Elmo-Tech/erp-frontend/blob/task/p0-01-ci-baseline/package-lock.json) |
| Backend Composer install/validation | **PASS** | https://github.com/Elmo-Tech/erp-backend/actions/runs/43857654487 |
| Backend PostgreSQL 18 migrations | **PASS** `migrate:fresh` on disposable CI DB | Same run |
| Backend PHPUnit | **PASS — 6 tests, 9 assertions** | Same run |
| Backend Docker build and API health smoke | **PASS** | Same run |
| Frontend `npm ci`, typecheck, Vitest, Vite build | **PASS — 4 tests in 2 files** | https://github.com/Elmo-Tech/erp-frontend/actions/runs/43857835260 |
| Frontend Docker/Nginx smoke | **PASS** | Same run |
| Live browser SPA-to-backend, cookie/CORS E2E | **NOT VERIFIED** | Separate smoke checks and mocked frontend health tests do not establish full end-to-end connectivity |
| Production/staging | **NOT DEPLOYED** | No release approval |

## Active task and interpretation

[P0-01 — CI & reproducible builds](https://github.com/Elmo-Tech/erp-backend/issues/1) is still **IN PROGRESS** until both draft PRs are reviewed and accepted and any remaining acceptance gap is resolved or explicitly approved. All CI gates on the implementation commits above have real successful GitHub Actions evidence. Avoid interpreting this as permission to merge or deploy.

Old integration-branch CI runs [Backend #43846548484](https://github.com/Elmo-Tech/erp-backend/actions/runs/43846548484) and [Frontend #43846673824](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43846673824) failed before producing accessible job steps or logs; their *original* failure reason is **NOT VERIFIED**. Newly created PR pipelines did expose missing lockfiles and passed after genuine locks were generated. The first npm 10.9.9 lock generation attempt failed with `Cannot read properties of null (reading 'edgesOut')`; npm **11.21.2** generated the lockfile and passed `npm ci`, and is pinned for frontend CI/Docker.

## Decisions and safety

No change to approved business architecture/ADR register in this task. Implementation-only CI tooling now pins npm 11.21.2 for compatibility with Node 22; backend remains PHP 8.4 / Composer 2, PostgreSQL 18. Temporary CI lockfile bootstrap workflows have been **removed** from both task branches. Accounting and legal isolation decisions remain unchanged. No new auth, financial posting API, customer data, or live migrations were used.

The current baseline **does not yet implement** Auth/MFA/RBAC, immutable GL posting, billing, audit outbox processing or an authorized staging rollout. The financial test validates company membership and a cross-company composite FK; broader authorization/transaction behavior remains for later tasks.

## Next exact action

**Stay on P0-01:** review and approve [backend PR #2](https://github.com/Elmo-Tech/erp-backend/pull/2) and [frontend PR #1](https://github.com/Elmo-Tech/erp-frontend/pull/1); confirm latest CI for their current commits; run or explicitly defer the real SPA/API connectivity smoke test with an isolated first-party test origin. Update Issue #1 and this status before closing P0-01. Do not merge without review.

**Next distinct task only after P0-01 closure:** **P0-02 — first-party Sanctum Auth, MFA, company membership and scoped RBAC**. See [EXECUTION_PLAN.md](EXECUTION_PLAN.md) and [MODULE_ROADMAP.md](MODULE_ROADMAP.md).

## Mandatory session handoff

For every task, read [START_HERE.md](START_HERE.md), [WORK_PROTOCOL.md](WORK_PROTOCOL.md), the relevant [handoff](handoffs/P0-01.md) and both `AGENTS.md`. Record issue/PR/CI URLs, exact pass/fail outcomes, unresolved gates and precise next step. GitHub is authoritative over chat memory.
