# P0-01 — GitHub Actions and build verification

**Evidence snapshot:** 2026-11-16; authoritative live checks may change after subsequent pushes.

## Current successful runs

- **Backend PASS:** https://github.com/Elmo-Tech/erp-backend/actions/runs/43857654487 — Composer lock install/validation, migrations against **disposable PostgreSQL 18**, **6 PHPUnit tests / 9 assertions**, Docker build, API health smoke.
- **Frontend PASS:** https://github.com/Elmo-Tech/erp-frontend/actions/runs/43857835260 — `npm ci` from committed lock, typecheck, **4 Vitest tests** (2 files), Vite production build, Docker/Nginx HTTP smoke.
- Authentic lockfile generation: [Composer bootstrap PASS](https://github.com/Elmo-Tech/erp-backend/actions/runs/43857462035); [npm bootstrap PASS](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43857627756). Both bootstrap workflows have been removed from their task branches.

## Findings / failure reconciliation

The first integration CI failed on both repos before producing visible steps:
- https://github.com/Elmo-Tech/erp-backend/actions/runs/43846548484
- https://github.com/Elmo-Tech/erp-frontend/actions/runs/43846673824

The GitHub connector returned empty steps, `BlobNotFound` logs, and two inaccessible annotations for each initial job. **Original reason NOT VERIFIED.** Once workflows were updated on PR branches, hosted runners did initialize, revealing the missing `composer.lock` and `package-lock.json` gates.

npm 10.9.9 on the hosted runner failed during *generation* of the frontend lockfile (`Cannot read properties of null (reading 'edgesOut')`). npm **11.21.2** generated a real v3 lockfile and passed full npm CI; frontend CI and Docker now explicitly pin that version.

## Remaining gates

Both PRs await human review. Separate health smokes do not prove an actual browser SPA-to-live-API request or future Sanctum/CORS behavior; **real E2E connectivity NOT VERIFIED**. These successful task-branch runs are not production or staging deployment verification. No user/financial data involved.

See [P0-01 handoff](handoffs/P0-01.md), [Project status](PROJECT_STATUS.md), backend [PR #2](https://github.com/Elmo-Tech/erp-backend/pull/2) and frontend [PR #1](https://github.com/Elmo-Tech/erp-frontend/pull/1).
